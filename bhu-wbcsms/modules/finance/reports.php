<?php
// ============================================
// FINANCE - REPORTS
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_FINANCE);

$reportType = isset($_GET['type']) ? $_GET['type'] : 'summary';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$message = '';
$error = '';

// Report data
$reportData = [];
$reportTitle = '';

switch ($reportType) {
    case 'summary':
        $reportTitle = 'Financial Summary Report';
        
        // Payment statistics
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as total_verified,
                SUM(amount) as total_amount
            FROM payments
        ");
        $reportData['payment_stats'] = $stmt->fetch();
        
        // Disbursement statistics
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_disbursements,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_disbursed,
                SUM(amount) as total_amount
            FROM disbursements
        ");
        $reportData['disbursement_stats'] = $stmt->fetch();
        
        // Monthly payment trends
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                COUNT(*) as count,
                SUM(amount) as total,
                SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as verified_amount
            FROM payments
            WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY month DESC
        ");
        $reportData['monthly_trends'] = $stmt->fetchAll();
        
        // Payment methods distribution
        $stmt = $pdo->query("
            SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(amount) as total
            FROM payments
            WHERE status = 'verified'
            GROUP BY payment_method
        ");
        $reportData['payment_methods'] = $stmt->fetchAll();
        
        break;
        
    case 'payments':
        $reportTitle = 'Payment Report';
        
        $stmt = $pdo->prepare("
            SELECT p.*, s.full_name as student_name, s.student_code,
                   u.full_name as verified_by_name
            FROM payments p
            JOIN students s ON p.student_id = s.student_id
            LEFT JOIN users u ON p.verified_by = u.user_id
            WHERE DATE(p.payment_date) BETWEEN ? AND ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData['payments'] = $stmt->fetchAll();
        
        break;
        
    case 'disbursements':
        $reportTitle = 'Disbursement Report';
        
        $stmt = $pdo->prepare("
            SELECT d.*, s.full_name as student_name, s.student_code,
                   u.full_name as finance_name
            FROM disbursements d
            JOIN students s ON d.student_id = s.student_id
            LEFT JOIN users u ON d.finance_user_id = u.user_id
            WHERE DATE(d.disbursement_date) BETWEEN ? AND ?
            ORDER BY d.disbursement_date DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData['disbursements'] = $stmt->fetchAll();
        
        break;
        
    case 'reconciliation':
        $reportTitle = 'Reconciliation Report';
        
        // Total payments verified
        $stmt = $pdo->prepare("
            SELECT 
                SUM(amount) as total_verified
            FROM payments
            WHERE status = 'verified'
            AND DATE(payment_date) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData['total_verified'] = $stmt->fetch()['total_verified'] ?? 0;
        
        // Total disbursed
        $stmt = $pdo->prepare("
            SELECT 
                SUM(amount) as total_disbursed
            FROM disbursements
            WHERE status = 'completed'
            AND DATE(disbursement_date) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData['total_disbursed'] = $stmt->fetch()['total_disbursed'] ?? 0;
        
        // Pending amounts
        $stmt = $pdo->prepare("
            SELECT 
                SUM(amount) as total_pending
            FROM payments
            WHERE status = 'pending'
            AND DATE(payment_date) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData['total_pending'] = $stmt->fetch()['total_pending'] ?? 0;
        
        // Difference
        $reportData['difference'] = $reportData['total_verified'] - $reportData['total_disbursed'];
        $reportData['reconciliation_status'] = $reportData['difference'] == 0 ? 'Balanced' : 'Unbalanced';
        
        break;
}

// Export functionality
if (isset($_GET['export'])) {
    $exportType = $_GET['export'] ?? 'csv';
    $filename = strtolower(str_replace(' ', '_', $reportTitle)) . '_' . date('Y-m-d');
    
    if ($exportType == 'csv') {
        exportToCSV($reportData, $filename . '.csv');
    } elseif ($exportType == 'pdf') {
        // PDF export will be implemented
        $message = 'PDF export coming soon.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Reports - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .report-stat {
            text-align: center;
            padding: 20px;
        }
        .report-stat .number {
            font-size: 32px;
            font-weight: 700;
        }
        .report-stat .label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .report-stat .icon {
            font-size: 30px;
            opacity: 0.2;
            display: block;
            margin-bottom: 10px;
        }
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .reconciliation-box {
            text-align: center;
            padding: 30px;
            border-radius: 15px;
        }
        .reconciliation-box.balanced {
            background: #d4edda;
            border: 2px solid #28a745;
        }
        .reconciliation-box.unbalanced {
            background: #f8d7da;
            border: 2px solid #dc3545;
        }
        .reconciliation-box .amount {
            font-size: 36px;
            font-weight: 700;
        }
        .print-only {
            display: none;
        }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            .report-card { box-shadow: none !important; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/finance_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav no-print">
            <h5><i class="fas fa-chart-bar"></i> Financial Reports</h5>
            <div>
                <button class="btn btn-success me-2" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="?type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=csv">
                            <i class="fas fa-file-csv"></i> Export as CSV
                        </a></li>
                        <li><a class="dropdown-item" href="?type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=pdf">
                            <i class="fas fa-file-pdf"></i> Export as PDF
                        </a></li>
                    </ul>
                </div>
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
        
        <!-- Filters -->
        <div class="filter-section no-print">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="type" class="form-select">
                        <option value="summary" <?php echo $reportType == 'summary' ? 'selected' : ''; ?>>Summary</option>
                        <option value="payments" <?php echo $reportType == 'payments' ? 'selected' : ''; ?>>Payments</option>
                        <option value="disbursements" <?php echo $reportType == 'disbursements' ? 'selected' : ''; ?>>Disbursements</option>
                        <option value="reconciliation" <?php echo $reportType == 'reconciliation' ? 'selected' : ''; ?>>Reconciliation</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="fas fa-sync"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Report Content -->
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-center">
                <h5><?php echo $reportTitle; ?></h5>
                <small class="text-muted">
                    Period: <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                </small>
            </div>
            <hr>
            
            <?php if ($reportType == 'summary'): ?>
                <!-- Summary Report -->
                <div class="row">
                    <!-- Payment Stats -->
                    <div class="col-md-6">
                        <h6 class="text-primary"><i class="fas fa-credit-card me-2"></i>Payment Statistics</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="report-stat">
                                    <div class="icon"><i class="fas fa-file-invoice"></i></div>
                                    <div class="number text-primary"><?php echo $reportData['payment_stats']['total_payments']; ?></div>
                                    <div class="label">Total Payments</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="report-stat">
                                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                                    <div class="number text-success"><?php echo formatCurrency($reportData['payment_stats']['total_verified'] ?? 0); ?></div>
                                    <div class="label">Verified Amount</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="report-stat">
                                    <div class="icon"><i class="fas fa-clock"></i></div>
                                    <div class="number text-warning"><?php echo $reportData['payment_stats']['pending']; ?></div>
                                    <div class="label">Pending</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="report-stat">
                                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                                    <div class="number text-danger"><?php echo $reportData['payment_stats']['rejected']; ?></div>
                                    <div class="label">Rejected</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Disbursement Stats -->
                    <div class="col-md-6">
                        <h6 class="text-success"><i class="fas fa-hand-holding-usd me-2"></i>Disbursement Statistics</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="report-stat">
                                    <div class="icon"><i class="fas fa-list"></i></div>
                                    <div class="number text-primary"><?php echo $reportData['disbursement_stats']['total_disbursements']; ?></div>
                                    <div class="label">Total Disbursements</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="report-stat">
                                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                                    <div class="number text-success"><?php echo formatCurrency($reportData['disbursement_stats']['total_disbursed'] ?? 0); ?></div>
                                    <div class="label">Total Disbursed</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="report-stat">
                                    <div class="icon"><i class="fas fa-clock"></i></div>
                                    <div class="number text-warning"><?php echo $reportData['disbursement_stats']['pending']; ?></div>
                                    <div class="label">Pending</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="report-stat">
                                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                                    <div class="number text-danger"><?php echo $reportData['disbursement_stats']['failed']; ?></div>
                                    <div class="label">Failed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Trends -->
                <?php if (!empty($reportData['monthly_trends'])): ?>
                <h6 class="mt-4"><i class="fas fa-chart-line me-2"></i>Monthly Payment Trends</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Payments</th>
                                <th>Total Amount</th>
                                <th>Verified Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['monthly_trends'] as $trend): ?>
                            <tr>
                                <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                                <td><?php echo $trend['count']; ?></td>
                                <td><?php echo formatCurrency($trend['total']); ?></td>
                                <td><?php echo formatCurrency($trend['verified_amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- Payment Methods -->
                <?php if (!empty($reportData['payment_methods'])): ?>
                <h6 class="mt-4"><i class="fas fa-credit-card me-2"></i>Payment Methods Distribution</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Count</th>
                                <th>Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['payment_methods'] as $method): ?>
                            <tr>
                                <td><?php echo ucfirst($method['payment_method']); ?></td>
                                <td><?php echo $method['count']; ?></td>
                                <td><?php echo formatCurrency($method['total']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
            <?php elseif ($reportType == 'payments'): ?>
                <!-- Payments Report -->
                <?php if (empty($reportData['payments'])): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No payments found in this period</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Verified By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalAmount = 0;
                                foreach ($reportData['payments'] as $payment): 
                                    $totalAmount += $payment['amount'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['student_name']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($payment['student_code']); ?></small>
                                    </td>
                                    <td><?php echo formatCurrency($payment['amount']); ?></td>
                                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td>
                                        <?php if ($payment['status'] == 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($payment['status'] == 'verified'): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php elseif ($payment['status'] == 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['verified_by_name'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td><strong>Total</strong></td>
                                    <td><strong><?php echo formatCurrency($totalAmount); ?></strong></td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($reportType == 'disbursements'): ?>
                <!-- Disbursements Report -->
                <?php if (empty($reportData['disbursements'])): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No disbursements found in this period</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalAmount = 0;
                                foreach ($reportData['disbursements'] as $disbursement): 
                                    $totalAmount += $disbursement['amount'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($disbursement['student_name']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($disbursement['student_code']); ?></small>
                                    </td>
                                    <td><?php echo formatCurrency($disbursement['amount']); ?></td>
                                    <td><?php echo ucfirst($disbursement['disbursement_method']); ?></td>
                                    <td><?php echo formatDate($disbursement['disbursement_date']); ?></td>
                                    <td><?php echo htmlspecialchars($disbursement['reference_number'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($disbursement['status'] == 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($disbursement['status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($disbursement['status'] == 'failed'): ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td><strong>Total</strong></td>
                                    <td><strong><?php echo formatCurrency($totalAmount); ?></strong></td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($reportType == 'reconciliation'): ?>
                <!-- Reconciliation Report -->
                <div class="reconciliation-box <?php echo $reportData['reconciliation_status'] == 'Balanced' ? 'balanced' : 'unbalanced'; ?>">
                    <h4>Reconciliation Status: <strong><?php echo $reportData['reconciliation_status']; ?></strong></h4>
                    
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="report-stat">
                                <div class="icon"><i class="fas fa-check-circle text-success"></i></div>
                                <div class="number text-success"><?php echo formatCurrency($reportData['total_verified']); ?></div>
                                <div class="label">Total Verified Payments</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="report-stat">
                                <div class="icon"><i class="fas fa-hand-holding-usd text-primary"></i></div>
                                <div class="number text-primary"><?php echo formatCurrency($reportData['total_disbursed']); ?></div>
                                <div class="label">Total Disbursed</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="report-stat">
                                <div class="icon"><i class="fas fa-balance-scale"></i></div>
                                <div class="number <?php echo $reportData['difference'] == 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo formatCurrency($reportData['difference']); ?>
                                </div>
                                <div class="label">Difference</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($reportData['total_pending'] > 0): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> 
                        There are <strong><?php echo formatCurrency($reportData['total_pending']); ?></strong> in pending payments.
                        Please verify these payments to complete the reconciliation.
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    // Helper function for CSV export
    function exportToCSV($data, $filename) {
        // Implementation will be added
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "Export functionality will be implemented.\n";
        exit;
    }
    ?>
</body>
</html>