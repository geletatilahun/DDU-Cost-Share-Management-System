<?php
// ============================================
// OFFICER - REPORTS
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_OFFICER);

$reportType = $_GET['type'] ?? 'summary';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get report data based on type
$reportData = [];
$reportTitle = '';

switch ($reportType) {
    case 'summary':
        $reportTitle = 'Summary Report';
        
        // Total applications
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM cost_share_applications");
        $reportData['total_applications'] = $stmt->fetch()['total'];
        
        // Applications by status
        $reportData['status_breakdown'] = $pdo->query("
            SELECT status, COUNT(*) as count 
            FROM cost_share_applications 
            GROUP BY status
        ")->fetchAll();
        
        // Total cost-share amounts
        $stmt = $pdo->query("SELECT SUM(requested_amount) as total FROM cost_share_applications WHERE status IN ('officer_approved', 'approved')");
        $reportData['total_amount'] = $stmt->fetch()['total'] ?? 0;
        
        // Students count
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE enrollment_status = 'active'");
        $reportData['total_students'] = $stmt->fetch()['total'];
        
        break;
        
    case 'applications':
        $reportTitle = 'Applications Report';
        
        $reportData['applications'] = $pdo->query("
            SELECT a.*, s.full_name, s.student_code, d.dept_name
            FROM cost_share_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN departments d ON s.department_id = d.dept_id
            WHERE DATE(a.application_date) BETWEEN '$startDate' AND '$endDate'
            ORDER BY a.created_at DESC
        ")->fetchAll();
        
        break;
        
    case 'students':
        $reportTitle = 'Student Report';
        
        $reportData['students'] = $pdo->query("
            SELECT s.*, d.dept_name,
                   (SELECT COUNT(*) FROM cost_share_applications WHERE student_id = s.student_id) as app_count
            FROM students s
            JOIN departments d ON s.department_id = d.dept_id
            WHERE s.enrollment_status = 'active'
            ORDER BY s.full_name
        ")->fetchAll();
        
        break;
        
    case 'financial':
        $reportTitle = 'Financial Report';
        
        $stmt = $pdo->query("
            SELECT SUM(total_cost_share) as total,
                   SUM(student_share) as student_total,
                   SUM(university_share) as university_total
            FROM cost_share_records
        ");
        $reportData['totals'] = $stmt->fetch();
        
        $reportData['payments'] = $pdo->query("
            SELECT p.*, s.full_name, s.student_code
            FROM payments p
            JOIN students s ON p.student_id = s.student_id
            WHERE DATE(p.payment_date) BETWEEN '$startDate' AND '$endDate'
            ORDER BY p.payment_date DESC
        ")->fetchAll();
        
        break;
}

// Export functionality
if (isset($_GET['export'])) {
    // Export logic will be implemented here
    // For now, we'll just redirect
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo SITE_NAME; ?></title>
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
    </style>
</head>
<body>
    <?php include '../../includes/officer_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-chart-bar"></i> Reports</h5>
            <div>
                <button class="btn btn-success me-2" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-primary" onclick="exportReport()">
                    <i class="fas fa-file-export"></i> Export
                </button>
            </div>
        </div>
        
        <!-- Report Filters -->
        <div class="report-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="type" class="form-select">
                        <option value="summary" <?php echo $reportType == 'summary' ? 'selected' : ''; ?>>Summary</option>
                        <option value="applications" <?php echo $reportType == 'applications' ? 'selected' : ''; ?>>Applications</option>
                        <option value="students" <?php echo $reportType == 'students' ? 'selected' : ''; ?>>Students</option>
                        <option value="financial" <?php echo $reportType == 'financial' ? 'selected' : ''; ?>>Financial</option>
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
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Report Content -->
        <div class="report-card">
            <h5><?php echo $reportTitle; ?></h5>
            <hr>
            
            <?php if ($reportType == 'summary'): ?>
                <!-- Summary Report -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="report-stat">
                            <div class="number text-primary"><?php echo $reportData['total_applications']; ?></div>
                            <div class="label">Total Applications</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="report-stat">
                            <div class="number text-success"><?php echo formatCurrency($reportData['total_amount'] ?? 0); ?></div>
                            <div class="label">Total Cost-Share</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="report-stat">
                            <div class="number text-info"><?php echo $reportData['total_students']; ?></div>
                            <div class="label">Active Students</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="report-stat">
                            <div class="number text-warning"><?php echo count($reportData['status_breakdown'] ?? []); ?></div>
                            <div class="label">Status Types</div>
                        </div>
                    </div>
                </div>
                
                <!-- Status Breakdown -->
                <h6 class="mt-4">Status Breakdown</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = $reportData['total_applications'];
                            foreach ($reportData['status_breakdown'] as $status): 
                            ?>
                            <tr>
                                <td><?php echo ucfirst(str_replace('_', ' ', $status['status'])); ?></td>
                                <td><?php echo $status['count']; ?></td>
                                <td>
                                    <?php 
                                    $percent = $total > 0 ? round(($status['count'] / $total) * 100, 1) : 0;
                                    ?>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?php echo $percent; ?>%">
                                            <?php echo $percent; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($reportType == 'applications'): ?>
                <!-- Applications Report -->
                <?php if (empty($reportData['applications'])): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No applications found in this period</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Department</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['applications'] as $app): ?>
                                <tr>
                                    <td>#<?php echo $app['application_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['full_name']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($app['student_code']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['dept_name']); ?></td>
                                    <td><?php echo formatCurrency($app['requested_amount']); ?></td>
                                    <td><?php echo formatDate($app['application_date']); ?></td>
                                    <td><?php echo getStatusBadge($app['status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td colspan="3"><strong>Total</strong></td>
                                    <td>
                                        <strong>
                                            <?php 
                                            $total = array_sum(array_column($reportData['applications'], 'requested_amount'));
                                            echo formatCurrency($total);
                                            ?>
                                        </strong>
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($reportType == 'students'): ?>
                <!-- Students Report -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Program</th>
                                <th>Year</th>
                                <th>Applications</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['students'] as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_code']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['dept_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['program']); ?></td>
                                <td>Year <?php echo $student['year_of_study']; ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $student['app_count']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($reportType == 'financial'): ?>
                <!-- Financial Report -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="report-stat">
                            <div class="number text-primary"><?php echo formatCurrency($reportData['totals']['total'] ?? 0); ?></div>
                            <div class="label">Total Cost-Share</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="report-stat">
                            <div class="number text-success"><?php echo formatCurrency($reportData['totals']['student_total'] ?? 0); ?></div>
                            <div class="label">Student Share</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="report-stat">
                            <div class="number text-info"><?php echo formatCurrency($reportData['totals']['university_total'] ?? 0); ?></div>
                            <div class="label">University Share</div>
                        </div>
                    </div>
                </div>
                
                <h6 class="mt-4">Payment Records</h6>
                <?php if (empty($reportData['payments'])): ?>
                    <div class="text-center py-3 text-muted">
                        <p>No payment records found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['payments'] as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($payment['student_code']); ?></small>
                                    </td>
                                    <td><?php echo formatCurrency($payment['amount']); ?></td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                    <td><?php echo getStatusBadge($payment['status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td><strong>Total</strong></td>
                                    <td>
                                        <strong>
                                            <?php 
                                            $total = array_sum(array_column($reportData['payments'], 'amount'));
                                            echo formatCurrency($total);
                                            ?>
                                        </strong>
                                    </td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function exportReport() {
            // Get current URL with export parameter
            const url = new URL(window.location.href);
            url.searchParams.set('export', '1');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>