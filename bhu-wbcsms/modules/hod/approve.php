<?php
// ============================================
// HOD - QUICK APPROVE
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_HOD);

$deptId = $_SESSION['dept_id'] ?? 0;

if (!$deptId) {
    die('Department not assigned.');
}

$message = '';
$error = '';

// Get pending applications
$stmt = $pdo->prepare("
    SELECT a.*, s.full_name as student_name, s.student_code, s.program
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ? AND a.status = 'officer_approved'
    ORDER BY a.created_at ASC
");
$stmt->execute([$deptId]);
$pendingApps = $stmt->fetchAll();

$pendingCount = count($pendingApps);

// Handle actions (same as applications.php but simplified)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        $applicationId = (int)$_POST['application_id'];
        
        if ($action === 'approve') {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE cost_share_applications 
                    SET status = 'hod_approved', 
                        hod_approved_by = ?,
                        hod_approval_date = NOW()
                    WHERE application_id = ? AND status = 'officer_approved'
                ");
                $stmt->execute([$_SESSION['user_id'], $applicationId]);
                
                if ($stmt->rowCount() > 0) {
                    $pdo->commit();
                    $message = 'Application approved successfully!';
                    Security::logEvent($_SESSION['user_id'], 'HOD_APPROVE', "Approved #{$applicationId}");
                } else {
                    $error = 'Application not found or not in correct status.';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Recent approvals
$stmt = $pdo->prepare("
    SELECT a.*, s.full_name as student_name
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    WHERE s.department_id = ? AND a.status = 'hod_approved'
    ORDER BY a.hod_approval_date DESC
    LIMIT 5
");
$stmt->execute([$deptId]);
$recentApprovals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Approve - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .app-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #f39c12;
            transition: all 0.3s;
        }
        .app-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .app-card .student-name {
            font-weight: 700;
            font-size: 18px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i {
            font-size: 60px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/hod_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-check-double"></i> Quick Approve</h5>
            <span class="badge bg-warning"><?php echo $pendingCount; ?> Pending</span>
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
        
        <?php if (empty($pendingApps)): ?>
            <div class="app-card">
                <div class="empty-state">
                    <i class="fas fa-check-circle text-success"></i>
                    <h5>All Caught Up!</h5>
                    <p>No pending applications to review.</p>
                    <span class="badge bg-success">✅ Everything is up to date</span>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($pendingApps as $app): ?>
            <div class="app-card">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <div class="student-name"><?php echo htmlspecialchars($app['student_name']); ?></div>
                        <div class="text-muted">
                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($app['student_code']); ?>
                            <span class="ms-3"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($app['program']); ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <strong><?php echo formatCurrency($app['requested_amount']); ?></strong>
                        <br>
                        <small class="text-muted"><?php echo formatDate($app['application_date']); ?></small>
                    </div>
                    <div class="col-md-4 text-end">
                        <form method="POST" class="d-inline">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="application_id" value="<?php echo $app['application_id']; ?>">
                            <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Approve this application?')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        <a href="applications.php?view=<?php echo $app['application_id']; ?>" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-eye"></i> Details
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Recent Approvals -->
        <?php if (!empty($recentApprovals)): ?>
        <div class="card mt-4">
            <div class="card-header bg-white">
                <i class="fas fa-history me-2"></i> Recent Approvals
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Approved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentApprovals as $app): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['student_name']); ?></td>
                                <td><?php echo formatCurrency($app['requested_amount']); ?></td>
                                <td><?php echo timeAgo($app['hod_approval_date']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>