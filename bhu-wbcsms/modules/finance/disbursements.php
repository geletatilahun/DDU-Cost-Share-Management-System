<?php
// ============================================
// FINANCE - DISBURSEMENTS (FIXED)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_FINANCE);

$message = '';
$error = '';
$editDisbursement = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // CREATE DISBURSEMENT
        if ($action === 'create') {
            $studentId = (int)$_POST['student_id'];
            $applicationId = (int)$_POST['application_id'];
            $amount = (float)$_POST['amount'];
            $disbursementDate = $_POST['disbursement_date'];
            $disbursementMethod = $_POST['disbursement_method'];
            $bankAccount = Security::sanitizeInput($_POST['bank_account']);
            $referenceNumber = Security::sanitizeInput($_POST['reference_number']);
            $remarks = Security::sanitizeInput($_POST['remarks']);
            
            try {
                $pdo->beginTransaction();
                
                // Check if cost_share_record exists, if not create one
                $stmt = $pdo->prepare("
                    SELECT record_id FROM cost_share_records 
                    WHERE student_id = ? 
                    ORDER BY record_id DESC LIMIT 1
                ");
                $stmt->execute([$studentId]);
                $record = $stmt->fetch();
                
                $recordId = null;
                
                if ($record) {
                    $recordId = $record['record_id'];
                } else {
                    // Create a new cost_share_record
                    $stmt = $pdo->prepare("
                        INSERT INTO cost_share_records (
                            student_id, ac_year_id, semester, total_cost_share, 
                            student_share, university_share, payment_status
                        ) VALUES (
                            ?, 
                            (SELECT ac_year_id FROM academic_years WHERE is_current = 1 LIMIT 1),
                            1,
                            ?,
                            0,
                            ?,
                            'pending'
                        )
                    ");
                    $stmt->execute([$studentId, $amount, $amount]);
                    $recordId = $pdo->lastInsertId();
                }
                
                // Insert disbursement
                $stmt = $pdo->prepare("
                    INSERT INTO disbursements (
                        student_id, record_id, application_id, amount, disbursement_date,
                        disbursement_method, bank_account, reference_number, remarks,
                        finance_user_id, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $studentId, $recordId, $applicationId, $amount, $disbursementDate,
                    $disbursementMethod, $bankAccount, $referenceNumber, $remarks,
                    $_SESSION['user_id']
                ]);
                
                $disbursementId = $pdo->lastInsertId();
                
                // Update application status
                $stmt = $pdo->prepare("
                    UPDATE cost_share_applications 
                    SET status = 'disbursed', final_approval_date = NOW()
                    WHERE application_id = ?
                ");
                $stmt->execute([$applicationId]);
                
                // Update cost_share_record
                $stmt = $pdo->prepare("
                    UPDATE cost_share_records 
                    SET payment_status = 'disbursed',
                        university_payment = university_payment + ?,
                        disbursement_date = NOW()
                    WHERE record_id = ?
                ");
                $stmt->execute([$amount, $recordId]);
                
                // Get student details
                $stmt = $pdo->prepare("
                    SELECT s.full_name, s.email, s.student_code
                    FROM students s
                    WHERE s.student_id = ?
                ");
                $stmt->execute([$studentId]);
                $student = $stmt->fetch();
                
                $pdo->commit();
                
                $message = 'Disbursement created successfully!';
                Security::logEvent($_SESSION['user_id'], 'CREATE_DISBURSEMENT', 
                                  "Created disbursement #{$disbursementId} for {$student['full_name']}");
                
                // Send notification
                if ($student) {
                    sendEmail($student['email'], 'Funds Disbursed', "
                        <h3>Funds Disbursed</h3>
                        <p>Dear {$student['full_name']},</p>
                        <p>We are pleased to inform you that <strong>" . formatCurrency($amount) . "</strong> has been disbursed to your account.</p>
                        <p><strong>Reference:</strong> {$referenceNumber}</p>
                        <p><strong>Method:</strong> " . ucfirst($disbursementMethod) . "</p>
                        <p>If you have any questions, please contact the Finance Department.</p>
                    ");
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // UPDATE DISBURSEMENT STATUS
        elseif ($action === 'update_status') {
            $disbursementId = (int)$_POST['disbursement_id'];
            $status = $_POST['status'];
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE disbursements 
                    SET status = ?, completed_at = NOW()
                    WHERE disbursement_id = ?
                ");
                $stmt->execute([$status, $disbursementId]);
                
                $message = 'Disbursement status updated!';
                Security::logEvent($_SESSION['user_id'], 'UPDATE_DISBURSEMENT', 
                                  "Updated disbursement #{$disbursementId} to {$status}");
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // DELETE DISBURSEMENT
        elseif ($action === 'delete') {
            $disbursementId = (int)$_POST['disbursement_id'];
            $stmt = $pdo->prepare("DELETE FROM disbursements WHERE disbursement_id = ? AND status = 'pending'");
            $stmt->execute([$disbursementId]);
            $message = 'Disbursement deleted successfully!';
        }
    }
}

// Get edit data
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM disbursements WHERE disbursement_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editDisbursement = $stmt->fetch();
}

// Get disbursements
$disbursements = $pdo->query("
    SELECT d.*, s.full_name as student_name, s.student_code,
           u.full_name as finance_name
    FROM disbursements d
    JOIN students s ON d.student_id = s.student_id
    LEFT JOIN users u ON d.finance_user_id = u.user_id
    ORDER BY d.created_at DESC
")->fetchAll();

// Get approved applications for disbursement - FIXED
$approvedApps = $pdo->query("
    SELECT a.*, s.full_name, s.student_code, s.student_id,
           r.record_id
    FROM cost_share_applications a
    JOIN students s ON a.student_id = s.student_id
    LEFT JOIN cost_share_records r ON r.student_id = s.student_id 
        AND r.ac_year_id = a.ac_year_id
    WHERE a.status = 'approved'
    AND NOT EXISTS (
        SELECT 1 FROM disbursements d 
        WHERE d.application_id = a.application_id AND d.status != 'failed'
    )
    ORDER BY a.created_at ASC
")->fetchAll();

// Get statistics
$stats = [];
$stats['total'] = count($disbursements);
$stats['pending'] = array_filter($disbursements, function($d) { return $d['status'] == 'pending'; });
$stats['completed'] = array_filter($disbursements, function($d) { return $d['status'] == 'completed'; });
$stats['failed'] = array_filter($disbursements, function($d) { return $d['status'] == 'failed'; });
$totalAmount = array_sum(array_column($disbursements, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disbursements - <?php echo SITE_NAME; ?></title>
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
        .stats-card .icon {
            float: right;
            font-size: 30px;
            opacity: 0.2;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <?php include '../../includes/finance_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-hand-holding-usd"></i> Disbursements</h5>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="fas fa-plus"></i> New Disbursement
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
                    <div class="icon"><i class="fas fa-list"></i></div>
                    <div class="number text-primary"><?php echo $stats['total']; ?></div>
                    <div class="label">Total Disbursements</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="number text-warning"><?php echo count($stats['pending']); ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="number text-success"><?php echo count($stats['completed']); ?></div>
                    <div class="label">Completed</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="number text-info"><?php echo formatCurrency($totalAmount); ?></div>
                    <div class="label">Total Amount</div>
                </div>
            </div>
        </div>
        
        <!-- Disbursements Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="disbursementTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disbursements as $disbursement): ?>
                        <tr>
                            <td>#<?php echo $disbursement['disbursement_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($disbursement['student_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($disbursement['student_code']); ?></small>
                            </td>
                            <td><?php echo formatCurrency($disbursement['amount']); ?></td>
                            <td><?php echo ucfirst($disbursement['disbursement_method']); ?></td>
                            <td><?php echo formatDate($disbursement['disbursement_date']); ?></td>
                            <td><small><?php echo htmlspecialchars($disbursement['reference_number'] ?? 'N/A'); ?></small></td>
                            <td>
                                <?php if ($disbursement['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php elseif ($disbursement['status'] == 'completed'): ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php elseif ($disbursement['status'] == 'failed'): ?>
                                    <span class="badge bg-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($disbursement['status'] == 'pending'): ?>
                                        <button class="btn btn-outline-success" onclick="completeDisbursement(<?php echo $disbursement['disbursement_id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="failDisbursement(<?php echo $disbursement['disbursement_id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteDisbursement(<?php echo $disbursement['disbursement_id']; ?>)">
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
    
    <!-- Create Disbursement Modal -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> New Disbursement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Select Approved Application *</label>
                            <select class="form-select" name="application_id" required onchange="updateDisbursementFields(this)">
                                <option value="">Select Application</option>
                                <?php foreach ($approvedApps as $app): ?>
                                    <option value="<?php echo $app['application_id']; ?>" 
                                            data-student="<?php echo $app['student_id']; ?>"
                                            data-record="<?php echo $app['record_id']; ?>"
                                            data-amount="<?php echo $app['requested_amount']; ?>"
                                            data-name="<?php echo htmlspecialchars($app['full_name']); ?>"
                                            data-code="<?php echo htmlspecialchars($app['student_code']); ?>">
                                        <?php echo htmlspecialchars($app['full_name'] . ' (' . $app['student_code'] . ') - ' . formatCurrency($app['requested_amount'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($approvedApps)): ?>
                                <small class="text-warning">No approved applications available for disbursement.</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Student Name</label>
                                    <input type="text" class="form-control" id="student_name" disabled>
                                    <input type="hidden" name="student_id" id="student_id">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Student ID</label>
                                    <input type="text" class="form-control" id="student_code" disabled>
                                    <input type="hidden" name="record_id" id="record_id">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Amount *</label>
                                    <input type="number" class="form-control" name="amount" id="amount" 
                                           step="100" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Disbursement Date *</label>
                                    <input type="date" class="form-control" name="disbursement_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Disbursement Method *</label>
                                    <select class="form-select" name="disbursement_method" required>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="check">Check</option>
                                        <option value="cash">Cash</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Bank Account</label>
                                    <input type="text" class="form-control" name="bank_account" 
                                           placeholder="Enter bank account number">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" name="reference_number" 
                                           placeholder="Enter reference number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" class="form-control" name="remarks" 
                                           placeholder="Additional remarks">
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            This will disburse funds to the student and update the application status to "Disbursed".
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Disbursement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#disbursementTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search disbursements:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ disbursements"
                }
            });
        });
        
        function updateDisbursementFields(select) {
            const option = select.options[select.selectedIndex];
            if (option.value) {
                document.getElementById('student_name').value = option.dataset.name;
                document.getElementById('student_code').value = option.dataset.code;
                document.getElementById('student_id').value = option.dataset.student;
                document.getElementById('record_id').value = option.dataset.record;
                document.getElementById('amount').value = option.dataset.amount;
            } else {
                document.getElementById('student_name').value = '';
                document.getElementById('student_code').value = '';
                document.getElementById('student_id').value = '';
                document.getElementById('record_id').value = '';
                document.getElementById('amount').value = '';
            }
        }
        
        function completeDisbursement(disbursementId) {
            if (confirm('Mark this disbursement as completed?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="disbursement_id" value="${disbursementId}">
                    <input type="hidden" name="status" value="completed">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function failDisbursement(disbursementId) {
            if (confirm('Mark this disbursement as failed?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="disbursement_id" value="${disbursementId}">
                    <input type="hidden" name="status" value="failed">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteDisbursement(disbursementId) {
            if (confirm('Are you sure you want to delete this disbursement?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="disbursement_id" value="${disbursementId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>