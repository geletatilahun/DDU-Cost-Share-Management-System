<?php
// ============================================
// OFFICER - COST-SHARE REGISTRATION
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_OFFICER);

$message = '';
$error = '';
$editRecord = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // REGISTER COST-SHARE
        if ($action === 'register') {
            $studentId = (int)$_POST['student_id'];
            $acYearId = (int)$_POST['ac_year_id'];
            $semester = (int)$_POST['semester'];
            $totalCostShare = (float)$_POST['total_cost_share'];
            $studentShare = (float)$_POST['student_share'];
            $universityShare = (float)$_POST['university_share'];
            $paymentDeadline = $_POST['payment_deadline'];
            
            // Validate
            if ($studentShare + $universityShare != $totalCostShare) {
                $error = 'Student share + University share must equal Total Cost-Share.';
            } else {
                try {
                    // Check if record exists
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM cost_share_records 
                        WHERE student_id = ? AND ac_year_id = ? AND semester = ?
                    ");
                    $stmt->execute([$studentId, $acYearId, $semester]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Cost-share record already exists for this student, year, and semester.';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO cost_share_records (
                                student_id, ac_year_id, semester, total_cost_share, 
                                student_share, university_share, payment_deadline
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $studentId, $acYearId, $semester, $totalCostShare,
                            $studentShare, $universityShare, $paymentDeadline
                        ]);
                        
                        $message = 'Cost-share registered successfully!';
                        Security::logEvent($_SESSION['user_id'], 'REGISTER_COST_SHARE', 
                                          "Registered cost-share for student ID: {$studentId}");
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // UPDATE COST-SHARE
        elseif ($action === 'update') {
            $recordId = (int)$_POST['record_id'];
            $totalCostShare = (float)$_POST['total_cost_share'];
            $studentShare = (float)$_POST['student_share'];
            $universityShare = (float)$_POST['university_share'];
            $paymentDeadline = $_POST['payment_deadline'];
            $paymentStatus = $_POST['payment_status'];
            
            if ($studentShare + $universityShare != $totalCostShare) {
                $error = 'Student share + University share must equal Total Cost-Share.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE cost_share_records 
                        SET total_cost_share = ?, student_share = ?, university_share = ?,
                            payment_deadline = ?, payment_status = ?
                        WHERE record_id = ?
                    ");
                    $stmt->execute([
                        $totalCostShare, $studentShare, $universityShare,
                        $paymentDeadline, $paymentStatus, $recordId
                    ]);
                    
                    $message = 'Cost-share updated successfully!';
                    Security::logEvent($_SESSION['user_id'], 'UPDATE_COST_SHARE', 
                                      "Updated cost-share record ID: {$recordId}");
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // DELETE COST-SHARE
        elseif ($action === 'delete') {
            $recordId = (int)$_POST['record_id'];
            
            $stmt = $pdo->prepare("DELETE FROM cost_share_records WHERE record_id = ?");
            $stmt->execute([$recordId]);
            $message = 'Cost-share record deleted successfully!';
            Security::logEvent($_SESSION['user_id'], 'DELETE_COST_SHARE', 
                              "Deleted cost-share record ID: {$recordId}");
        }
    }
}

// Get edit data
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM cost_share_records WHERE record_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editRecord = $stmt->fetch();
}

// Get all cost-share records
$records = $pdo->query("
    SELECT r.*, 
           s.full_name as student_name, 
           s.student_code,
           d.dept_name,
           y.year_name
    FROM cost_share_records r
    JOIN students s ON r.student_id = s.student_id
    JOIN departments d ON s.department_id = d.dept_id
    JOIN academic_years y ON r.ac_year_id = y.ac_year_id
    ORDER BY r.created_at DESC
")->fetchAll();

// Get students for dropdown
$students = $pdo->query("
    SELECT s.*, d.dept_name 
    FROM students s
    JOIN departments d ON s.department_id = d.dept_id
    WHERE s.enrollment_status = 'active'
    ORDER BY s.full_name
")->fetchAll();

// Get academic years for dropdown
$academicYears = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();

// Get stats
$totalRecords = count($records);
$totalAmount = array_sum(array_column($records, 'total_cost_share'));
$paidRecords = array_filter($records, function($r) { return $r['payment_status'] == 'paid' || $r['payment_status'] == 'verified'; });
$pendingRecords = array_filter($records, function($r) { return $r['payment_status'] == 'pending'; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost-Share Registration - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .stats-card .number {
            font-size: 28px;
            font-weight: 700;
        }
        .stats-card .label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .amount-breakdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
        }
        .amount-breakdown .label {
            color: #7f8c8d;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .amount-breakdown .value {
            font-size: 18px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <?php include '../../includes/officer_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-coins"></i> Cost-Share Registration</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
                <i class="fas fa-plus"></i> Register New
            </button>
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
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-primary"><?php echo $totalRecords; ?></div>
                    <div class="label">Total Records</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-success"><?php echo formatCurrency($totalAmount); ?></div>
                    <div class="label">Total Cost-Share</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-warning"><?php echo count($pendingRecords); ?></div>
                    <div class="label">Pending Payment</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-success"><?php echo count($paidRecords); ?></div>
                    <div class="label">Paid / Verified</div>
                </div>
            </div>
        </div>
        
        <!-- Records Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="costShareTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Total Amount</th>
                            <th>Student Share</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <td>#<?php echo $record['record_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($record['student_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($record['student_code']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($record['year_name']); ?></td>
                            <td>Semester <?php echo $record['semester']; ?></td>
                            <td><?php echo formatCurrency($record['total_cost_share']); ?></td>
                            <td><?php echo formatCurrency($record['student_share']); ?></td>
                            <td>
                                <?php echo getStatusBadge($record['payment_status']); ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?php echo $record['record_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($record['payment_status'] == 'pending'): ?>
                                    <button class="btn btn-outline-danger" onclick="deleteRecord(<?php echo $record['record_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Register Cost-Share</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="registerForm">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="register">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Student *</label>
                                    <select class="form-select" name="student_id" required>
                                        <option value="">Select Student</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['student_id']; ?>">
                                                <?php echo htmlspecialchars($student['student_code'] . ' - ' . $student['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Academic Year *</label>
                                    <select class="form-select" name="ac_year_id" required>
                                        <option value="">Select Year</option>
                                        <?php foreach ($academicYears as $year): ?>
                                            <option value="<?php echo $year['ac_year_id']; ?>" 
                                                <?php echo $year['is_current'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($year['year_name']); ?>
                                                <?php echo $year['is_current'] ? '(Current)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Semester *</label>
                                    <select class="form-select" name="semester" required>
                                        <option value="1">Semester 1</option>
                                        <option value="2">Semester 2</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Payment Deadline *</label>
                                    <input type="date" class="form-control" name="payment_deadline" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="amount-breakdown">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="label">Total Cost-Share</div>
                                            <div class="value">
                                                <input type="number" class="form-control" id="totalCostShare" 
                                                       name="total_cost_share" step="100" min="0" 
                                                       placeholder="Total" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="label">Student Share</div>
                                            <div class="value">
                                                <input type="number" class="form-control" id="studentShare" 
                                                       name="student_share" step="100" min="0" 
                                                       placeholder="Student" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="label">University Share</div>
                                            <div class="value">
                                                <input type="number" class="form-control" id="universityShare" 
                                                       name="university_share" step="100" min="0" 
                                                       placeholder="University" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Note:</strong> Student Share + University Share must equal Total Cost-Share.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Register Cost-Share</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <?php if ($editRecord): ?>
    <div class="modal fade show" id="editModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Cost-Share</h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="record_id" value="<?php echo $editRecord['record_id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Student</label>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT full_name, student_code FROM students WHERE student_id = ?");
                                    $stmt->execute([$editRecord['student_id']]);
                                    $student = $stmt->fetch();
                                    ?>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars($student['full_name'] . ' (' . $student['student_code'] . ')'); ?>" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Academic Year</label>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT year_name FROM academic_years WHERE ac_year_id = ?");
                                    $stmt->execute([$editRecord['ac_year_id']]);
                                    $year = $stmt->fetch();
                                    ?>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($year['year_name']); ?>" disabled>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Semester</label>
                                    <input type="text" class="form-control" 
                                           value="Semester <?php echo $editRecord['semester']; ?>" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Payment Deadline</label>
                                    <input type="date" class="form-control" name="payment_deadline" 
                                           value="<?php echo $editRecord['payment_deadline']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Total Cost-Share</label>
                                    <input type="number" class="form-control" name="total_cost_share" 
                                           value="<?php echo $editRecord['total_cost_share']; ?>" step="100" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Student Share</label>
                                    <input type="number" class="form-control" name="student_share" 
                                           value="<?php echo $editRecord['student_share']; ?>" step="100" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">University Share</label>
                                    <input type="number" class="form-control" name="university_share" 
                                           value="<?php echo $editRecord['university_share']; ?>" step="100" min="0" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Status</label>
                            <select class="form-select" name="payment_status">
                                <option value="pending" <?php echo $editRecord['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="partial" <?php echo $editRecord['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial</option>
                                <option value="paid" <?php echo $editRecord['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="verified" <?php echo $editRecord['payment_status'] == 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="disbursed" <?php echo $editRecord['payment_status'] == 'disbursed' ? 'selected' : ''; ?>>Disbursed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#costShareTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search records:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ records"
                }
            });
        });
        
        // Auto-calculate university share
        document.getElementById('totalCostShare').addEventListener('input', calculateShares);
        document.getElementById('studentShare').addEventListener('input', calculateShares);
        
        function calculateShares() {
            const total = parseFloat(document.getElementById('totalCostShare').value) || 0;
            const student = parseFloat(document.getElementById('studentShare').value) || 0;
            const university = total - student;
            
            if (university >= 0) {
                document.getElementById('universityShare').value = university;
            } else {
                document.getElementById('universityShare').value = 0;
                document.getElementById('studentShare').value = total;
            }
        }
        
        function deleteRecord(recordId) {
            if (confirm('Are you sure you want to delete this record?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="record_id" value="${recordId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>