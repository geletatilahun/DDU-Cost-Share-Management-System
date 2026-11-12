<?php
// ============================================
// STUDENT SIDEBAR
// ============================================

$currentPage = basename($_SERVER['PHP_SELF']);

// Get student ID
$studentId = $_SESSION['student_id'] ?? 0;

// Get pending application count
$pendingCount = 0;
if ($studentId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM cost_share_applications 
        WHERE student_id = ? AND status = 'pending'
    ");
    $stmt->execute([$studentId]);
    $pendingCount = $stmt->fetch()['total'] ?? 0;
}
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
    color: #9b59b6;
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
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
    color: white;
    box-shadow: 0 5px 15px rgba(155, 89, 182, 0.3);
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
        <small>Student Panel</small>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <hr class="nav-divider">
        <a class="nav-link <?php echo $currentPage == 'apply.php' ? 'active' : ''; ?>" href="apply.php">
            <i class="fas fa-pen-fancy"></i> <span>Apply</span>
        </a>
        <a class="nav-link <?php echo $currentPage == 'status.php' ? 'active' : ''; ?>" href="status.php">
            <i class="fas fa-info-circle"></i> <span>Status</span>
            <?php if ($pendingCount > 0): ?>
                <span class="badge bg-danger"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link <?php echo $currentPage == 'payment.php' ? 'active' : ''; ?>" href="payment.php">
            <i class="fas fa-credit-card"></i> <span>Payment</span>
        </a>
        <hr class="nav-divider">
        <a class="nav-link <?php echo $currentPage == 'notices.php' ? 'active' : ''; ?>" href="notices.php">
            <i class="fas fa-bullhorn"></i> <span>Notices</span>
        </a>
        <a class="nav-link <?php echo $currentPage == 'feedback.php' ? 'active' : ''; ?>" href="feedback.php">
            <i class="fas fa-comment"></i> <span>Feedback</span>
        </a>
        <hr class="nav-divider">
        <a class="nav-link <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
            <i class="fas fa-user"></i> <span>Profile</span>
        </a>
        <a class="nav-link text-danger" href="../../logout.php">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </nav>
</div>