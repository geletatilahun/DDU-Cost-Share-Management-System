<?php
// ============================================
// HOD - DEPARTMENT REPORTS
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_HOD);

$deptId = $_SESSION['dept_id'] ?? 0;

if (!$deptId) {
    die('Department not assigned.');
}

$reportType = isset($_GET['type']) ? $_GET['type'] : 'summary';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get department info
$stmt = $pdo->prepare("SELECT * FROM departments WHERE dept_id = ?");
$stmt->execute([$deptId]);
$department = $stmt->fetch();

// Report data
$reportData = [];
$reportTitle = '';

switch ($reportType) {
    case 'summary':
        $reportTitle = 'Department Summary Report';
        
        // Total students
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE department_id = ? AND enrollment_status = 'active'");
        $stmt->execute([$deptId]);
        $reportData['total_students'] = $stmt->fetch()['total'];
        
        // Applications stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'officer_approved' THEN 1 ELSE 0 END) as officer_approved,
                SUM(CASE WHEN status = 'hod_approved' THEN 1 ELSE 0 END) as hod_approved,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status IN ('hod_approved', 'approved') THEN requested_amount ELSE 0 END) as total_amount
            FROM cost_share_applications a
            JOIN students s ON a.student_id = s.student_id
            WHERE s.department_id = ?
        ");
        $stmt->execute([$deptId]);
        $reportData['app_stats'] = $stmt->fetch();
        
        // Monthly applications
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(a.created_at, '%Y-%m') as month,
                COUNT(*) as count,
                SUM(a.requested_amount) as amount
            FROM cost_share_applications a
            JOIN students s ON a.student_id = s.student_id
            WHERE s.department_id = ?
            GROUP BY DATE_FORMAT(a.created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");
        $stmt->execute([$deptId]);
        $reportData['monthly_apps'] = $stmt->fetchAll();
        
        break;
        
    case 'applications':
        $reportTitle = 'Applications Report';
        
        $stmt = $pdo->prepare("
            SELECT a.*, s.full_name, s.student_code, s.program
            FROM cost_share_applications a
            JOIN students s ON a.student_id = s.student_id
            WHERE s.department_id = ? AND DATE(a.application_date) BETWEEN ? AND ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$deptId, $startDate, $endDate]);
        $reportData['applications'] = $stmt->fetchAll();
        
        break;
        
    case 'students':
        $reportTitle = 'Student Report';
        
        $stmt = $pdo->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM cost_share_applications WHERE student_id = s.student_id) as app_count
            FROM students s
            WHERE s.department_id = ?
            ORDER BY s.full_name
        ");
        $stmt->execute([$deptId]);
        $reportData['students'] = $stmt->fetchAll();
        
        break;
        
    case 'financial':
        $reportTitle = 'Financial Report';
        
        // Totals
        $stmt = $pdo->prepare("
            SELECT 
                SUM(a.requested_amount) as total_requested,
                SUM(CASE WHEN a.status IN ('hod_approved', 'approved') THEN a.requested_amount ELSE 0 END) as total_approved
            FROM cost_share_applications a
            JOIN students s ON a.student_id = s.student_id
            WHERE s.department_id = ?
        ");
        $stmt->execute([$deptId]);
        $reportData['financial_totals'] = $stmt->fetch();
        
        // Approved applications
        $stmt = $pdo->prepare("
            SELECT a.*, s.full_name, s.student_code
            FROM cost_share_applications a
            JOIN students s ON a.student_id = s.student_id
            WHERE s.department_id = ? AND a.status IN ('hod_approved', 'approved')
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$deptId]);
        $reportData['approved_apps'] = $stmt->fetchAll();
        
        break;
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
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/hod_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-chart-bar"></i> Department Reports</h5>
            <div>
                <button class="btn btn-success me-2" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-primary" onclick="exportReport()">
                    <i class="fas fa-file-export"></i> Export
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-section">
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
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fas fa-sync"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Report Content -->
        <div class="report-card">
            <h5><?php echo $reportTitle; ?></h5>
            <small class="text-muted">Department: <?php echo htmlspecialchars($department['dept_name']); ?></small>
            <hr>
            
            <?php if ($reportType == 'summary'): ?>
                <!-- Summary Report -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="report-stat">
                            <div class="number text-primary"><?php echo $reportData['total_students']; ?></div>
                            <div class="label">Total Students</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="report-stat">
                            <div class="number text-warning"><?php echo $reportData['app_stats']['pending']; ?></div>
                            <div class="label">Pending Applications</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="report-stat">
                            <div class="number text-success"><?php echo $reportData['app_stats']['approved'] + $reportData['app_stats']['hod_approved']; ?></div>
                            <div class="label">Approved Applications</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="report-stat">
                            <div class="number text-info"><?php echo formatCurrency($reportData['app_stats']['total_amount'] ?? 0); ?></div>
                            <div class="label">Total Amount Approved</div>
                        </div>
                    </div>
                </div>
                
                <h6 class="mt-4">Status Breakdown</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Pending</td>
                                <td><?php echo $reportData['app_stats']['pending']; ?></td>
                            </tr>
                            <tr>
                                <td>Officer Approved</td>
                                <td><?php echo $reportData['app_stats']['officer_approved']; ?></td>
                            </tr>
                            <tr>
                                <td>HOD Approved</td>
                                <td><?php echo $reportData['app_stats']['hod_approved']; ?></td>
                            </tr>
                            <tr>
                                <td>Final Approved</td>
                                <td><?php echo $reportData['app_stats']['approved']; ?></td>
                            </tr>
                            <tr>
                                <td>Rejected</td>
                                <td><?php echo $reportData['app_stats']['rejected']; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($reportData['monthly_apps'])): ?>
                <h6 class="mt-4">Monthly Applications</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Count</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['monthly_apps'] as $month): ?>
                            <tr>
                                <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                <td><?php echo $month['count']; ?></td>
                                <td><?php echo formatCurrency($month['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
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
                                    <th>Program</th>
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
                                    <td><?php echo htmlspecialchars($app['program']); ?></td>
                                    <td><?php echo formatCurrency($app['requested_amount']); ?></td>
                                    <td><?php echo formatDate($app['application_date']); ?></td>
                                    <td><?php echo getStatusBadge($app['status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($reportType == 'students'): ?>
                <!-- Students Report -->
                <?php if (empty($reportData['students'])): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <p>No students found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Program</th>
                                    <th>Year</th>
                                    <th>Status</th>
                                    <th>Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['students'] as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_code']); ?></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['program']); ?></td>
                                    <td>Year <?php echo $student['year_of_study']; ?></td>
                                    <td><?php echo getStatusBadge($student['enrollment_status']); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $student['app_count']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($reportType == 'financial'): ?>
                <!-- Financial Report -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="report-stat">
                            <div class="number text-primary"><?php echo formatCurrency($reportData['financial_totals']['total_requested'] ?? 0); ?></div>
                            <div class="label">Total Requested</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="report-stat">
                            <div class="number text-success"><?php echo formatCurrency($reportData['financial_totals']['total_approved'] ?? 0); ?></div>
                            <div class="label">Total Approved</div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($reportData['approved_apps'])): ?>
                <h6 class="mt-4">Approved Applications</h6>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['approved_apps'] as $app): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['full_name']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($app['student_code']); ?></small>
                                </td>
                                <td><?php echo formatCurrency($app['requested_amount']); ?></td>
                                <td><?php echo formatDate($app['application_date']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td><strong>Total</strong></td>
                                <td>
                                    <strong>
                                        <?php 
                                        $total = array_sum(array_column($reportData['approved_apps'], 'requested_amount'));
                                        echo formatCurrency($total);
                                        ?>
                                    </strong>
                                </td>
                                <td></td>
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
            alert('Export functionality will be implemented.');
        }
    </script>
</body>
</html>