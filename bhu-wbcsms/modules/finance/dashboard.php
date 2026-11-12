<?php
// ============================================
// FINANCE - DASHBOARD
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_FINANCE);

$message = '';
$error = '';

// Get statistics
$stats = [];

// Pending verifications
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'");
$stats['pending_verifications'] = $stmt->fetch()['total'];

// Total payments
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments");
$stats['total_payments'] = $stmt->fetch()['total'];

// Total amount verified
$stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status = 'verified'");
$stats['total_verified'] = $stmt->fetch()['total'] ?? 0;

// Total amount pending
$stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status = 'pending'");
$stats['total_pending'] = $stmt->fetch()['total'] ?? 0;

// Pending disbursements
$stmt = $pdo->query("SELECT COUNT(*) as total FROM disbursements WHERE status = 'pending'");
$stats['pending_disbursements'] = $stmt->fetch()['total'];

// Total disbursed
$stmt = $pdo->query("SELECT SUM(amount) as total FROM disbursements WHERE status = 'completed'");
$stats['total_disbursed'] = $stmt->fetch()['total'] ?? 0;

// Today's payments
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE DATE(payment_date) = CURDATE()");
$stats['today_payments'] = $stmt->fetch()['total'];

// This week's payments
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE YEARWEEK(payment_date) = YEARWEEK(CURDATE())");
$stats['weekly_payments'] = $stmt->fetch()['total'];

// Recent payments
$recentPayments = $pdo->query("
    SELECT p.*, s.full_name as student_name, s.student_code
    FROM payments p
    JOIN students s ON p.student_id = s.student_id
    ORDER BY p.created_at DESC
    LIMIT 10
")->fetchAll();

// Recent disbursements
$recentDisbursements = $pdo->query("
    SELECT d.*, s.full_name as student_name, s.student_code
    FROM disbursements d
    JOIN students s ON d.student_id = s.student_id
    ORDER BY d.created_at DESC
    LIMIT 5
")->fetchAll();

// Payment method distribution
$paymentMethods = $pdo->query("
    SELECT payment_method, COUNT(*) as count, SUM(amount) as total
    FROM payments
    WHERE status = 'verified'
    GROUP BY payment_method
")->fetchAll();

// Monthly payment trends
$monthlyData = $pdo->query("
    SELECT 
        MONTHNAME(payment_date) as month,
        COUNT(*) as count,
        SUM(amount) as total
    FROM payments
    WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND status = 'verified'
    GROUP BY MONTH(payment_date)
    ORDER BY MONTH(payment_date)
")->fetchAll();

$chartLabels = [];
$chartData = [];
foreach ($monthlyData as $row) {
    $chartLabels[] = $row['month'];
    $chartData[] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - <?php echo SITE_NAME; ?></title>
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
            border-left: 4px solid #e74c3c;
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
        
        .payment-item, .disbursement-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s;
        }
        .payment-item:hover, .disbursement-item:hover {
            background: #f8f9fa;
        }
        .payment-item:last-child, .disbursement-item:last-child {
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
            border-color: #e74c3c;
            color: #e74c3c;
        }
        .quick-action-btn i {
            font-size: 30px;
            display: block;
            margin-bottom: 10px;
        }
        .amount-large {
            font-size: 24px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <?php include '../../includes/finance_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <div>
                <h5><i class="fas fa-chart-pie"></i> Finance Dashboard</h5>
                <small>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
            </div>
            <div>
                <span class="badge bg-success me-2"><i class="fas fa-circle"></i> System Online</span>
                <span class="badge bg-info"><?php echo date('l, F d, Y h:i A'); ?></span>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-warning">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $stats['pending_verifications']; ?></div>
                    <div class="stat-label">Pending Verifications</div>
                    <small class="text-danger">Requires your action</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo formatCurrency($stats['total_verified']); ?></div>
                    <div class="stat-label">Total Verified</div>
                    <small class="text-muted">All time</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-danger">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-number"><?php echo $stats['pending_disbursements']; ?></div>
                    <div class="stat-label">Pending Disbursements</div>
                    <small class="text-danger">Needs processing</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-info">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number"><?php echo formatCurrency($stats['total_disbursed']); ?></div>
                    <div class="stat-label">Total Disbursed</div>
                    <small class="text-muted">Funds released</small>
                </div>
            </div>
        </div>
        
        <!-- Second Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-primary">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-number"><?php echo $stats['today_payments']; ?></div>
                    <div class="stat-label">Today's Payments</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card" style="border-left-color: #9b59b6;">
                    <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="stat-number"><?php echo $stats['weekly_payments']; ?></div>
                    <div class="stat-label">This Week</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card" style="border-left-color: #e67e22;">
                    <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                    <div class="stat-number"><?php echo $stats['total_payments']; ?></div>
                    <div class="stat-label">Total Payments</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card" style="border-left-color: #2ecc71;">
                    <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM disbursements WHERE status = 'completed'");
                    $disbursedCount = $stmt->fetch()['total'];
                    ?>
                    <div class="stat-number"><?php echo $disbursedCount; ?></div>
                    <div class="stat-label">Disbursements Made</div>
                </div>
            </div>
        </div>
        
        <!-- Charts & Quick Actions -->
        <div class="row g-4">
            <!-- Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i> Monthly Payment Trends
                    </div>
                    <div class="card-body">
                        <?php if (empty($chartData) || array_sum($chartData) == 0): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-chart-line fa-3x mb-3"></i>
                                <p>No payment data available</p>
                            </div>
                        <?php else: ?>
                            <canvas id="paymentChart" height="250"></canvas>
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
                                <a href="payments.php" class="quick-action-btn">
                                    <i class="fas fa-credit-card text-warning"></i>
                                    <span class="label">Verify Payments</span>
                                    <?php if ($stats['pending_verifications'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $stats['pending_verifications']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="disbursements.php" class="quick-action-btn">
                                    <i class="fas fa-hand-holding-usd text-success"></i>
                                    <span class="label">Disburse Funds</span>
                                    <?php if ($stats['pending_disbursements'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $stats['pending_disbursements']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="reports.php" class="quick-action-btn">
                                    <i class="fas fa-chart-bar text-primary"></i>
                                    <span class="label">Reports</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="settings.php" class="quick-action-btn">
                                    <i class="fas fa-cog text-secondary"></i>
                                    <span class="label">Settings</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Payments & Disbursements -->
        <div class="row g-4 mt-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-credit-card me-2"></i> Recent Payments
                        </div>
                        <a href="payments.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($recentPayments)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No payments found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $payment): ?>
                            <div class="payment-item">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <strong><?php echo htmlspecialchars($payment['student_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($payment['student_code']); ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo formatCurrency($payment['amount']); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?php if ($payment['status'] == 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($payment['status'] == 'verified'): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php elseif ($payment['status'] == 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <small class="text-muted"><?php echo timeAgo($payment['created_at']); ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-hand-holding-usd me-2"></i> Recent Disbursements
                        </div>
                        <a href="disbursements.php" class="btn btn-sm btn-success">View All</a>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($recentDisbursements)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No disbursements found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentDisbursements as $disbursement): ?>
                            <div class="disbursement-item">
                                <div class="row align-items-center">
                                    <div class="col-md-5">
                                        <strong><?php echo htmlspecialchars($disbursement['student_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($disbursement['student_code']); ?></small>
                                    </div>
                                    <div class="col-md-4">
                                        <?php echo formatCurrency($disbursement['amount']); ?>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <?php if ($disbursement['status'] == 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($disbursement['status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($disbursement['status'] == 'failed'): ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php endif; ?>
                                    </div>
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
    <script>
        <?php if (!empty($chartData) && array_sum($chartData) > 0): ?>
        const ctx = document.getElementById('paymentChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Payment Amount (ETB)',
                    data: <?php echo json_encode($chartData); ?>,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'ETB ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>