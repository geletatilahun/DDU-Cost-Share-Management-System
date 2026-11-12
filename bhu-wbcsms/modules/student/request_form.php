<?php
// ============================================
// STUDENT - REQUEST COST-SHARE FORM
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_STUDENT);

$user = Auth::getCurrentUser();
$studentId = $user['student_id'] ?? null;
$message = '';
$error = '';

if (!$studentId) {
    die('Student record not found. Please contact administrator.');
}

// Get student info
$stmt = $pdo->prepare("
    SELECT s.*, d.dept_name, d.dept_id 
    FROM students s
    JOIN departments d ON s.department_id = d.dept_id
    WHERE s.student_id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    die('Student record not found.');
}

// Get HOD for the department
$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.email 
    FROM users u
    WHERE u.role_id = 3 AND u.dept_id = ? AND u.is_active = 1
    LIMIT 1
");
$stmt->execute([$student['department_id']]);
$hod = $stmt->fetch();

// Check if student has already requested a form
$stmt = $pdo->prepare("
    SELECT * FROM cost_share_form_requests 
    WHERE student_id = ? AND status = 'pending'
    ORDER BY request_id DESC LIMIT 1
");
$stmt->execute([$studentId]);
$pendingRequest = $stmt->fetch();

// Check if student has an approved form to fill
$stmt = $pdo->prepare("
    SELECT * FROM cost_share_forms 
    WHERE student_id = ? AND status = 'pending'
    ORDER BY form_id DESC LIMIT 1
");
$stmt->execute([$studentId]);
$pendingForm = $stmt->fetch();

// Check if student already has a submitted form
$stmt = $pdo->prepare("
    SELECT * FROM cost_share_forms 
    WHERE student_id = ? AND status IN ('pending', 'approved')
    ORDER BY form_id DESC LIMIT 1
");
$stmt->execute([$studentId]);
$existingForm = $stmt->fetch();

// Handle form request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Request Form
        if ($action === 'request_form') {
            if (!$hod) {
                $error = 'No HOD assigned to your department. Please contact administrator.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO cost_share_form_requests (
                            student_id, hod_id, request_date, status
                        ) VALUES (?, ?, NOW(), 'pending')
                    ");
                    $stmt->execute([$studentId, $hod['user_id']]);
                    
                    $message = 'Form request sent to HOD successfully!';
                    Security::logEvent($_SESSION['user_id'], 'REQUEST_FORM', 'Requested cost-share form');
                    
                    // Send notification to HOD
                    sendEmail($hod['email'], 'Cost-Share Form Request', "
                        <h3>Cost-Share Form Request</h3>
                        <p><strong>Student:</strong> {$student['full_name']}</p>
                        <p><strong>Student ID:</strong> {$student['student_code']}</p>
                        <p><strong>Department:</strong> {$student['dept_name']}</p>
                        <p><a href='" . SITE_URL . "modules/hod/form_requests.php'>Review Request</a></p>
                    ");
                    
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // Submit Filled Form
        elseif ($action === 'submit_form') {
            $requestedAmount = (float)$_POST['requested_amount'];
            $reason = Security::sanitizeInput($_POST['reason']);
            $academicYear = Security::sanitizeInput($_POST['academic_year']);
            $semester = (int)$_POST['semester'];
            
            if ($requestedAmount <= 0) {
                $error = 'Please enter a valid amount.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Get academic year
                    $stmt = $pdo->prepare("SELECT ac_year_id FROM academic_years WHERE year_code = ? AND is_current = 1");
                    $stmt->execute([$academicYear]);
                    $year = $stmt->fetch();
                    
                    if (!$year) {
                        throw new Exception('Invalid academic year.');
                    }
                    
                    // Create form record
                    $formData = json_encode([
                        'requested_amount' => $requestedAmount,
                        'reason' => $reason,
                        'academic_year' => $academicYear,
                        'semester' => $semester
                    ]);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO cost_share_forms (
                            student_id, academic_year_id, semester, form_data, 
                            status, student_submitted_date
                        ) VALUES (?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([
                        $studentId,
                        $year['ac_year_id'],
                        $semester,
                        $formData
                    ]);
                    
                    $pdo->commit();
                    
                    $message = 'Form submitted for HOD approval!';
                    Security::logEvent($_SESSION['user_id'], 'SUBMIT_FORM', 'Submitted cost-share form');
                    
                    // Notify HOD
                    if ($hod) {
                        sendEmail($hod['email'], 'Cost-Share Form Submitted', "
                            <h3>Cost-Share Form Submitted</h3>
                            <p><strong>Student:</strong> {$student['full_name']}</p>
                            <p><strong>Student ID:</strong> {$student['student_code']}</p>
                            <p><strong>Amount:</strong> " . formatCurrency($requestedAmount) . "</p>
                            <p><a href='" . SITE_URL . "modules/hod/form_requests.php'>Review Form</a></p>
                        ");
                    }
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get request history
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name as hod_name
    FROM cost_share_form_requests r
    LEFT JOIN users u ON r.hod_id = u.user_id
    WHERE r.student_id = ?
    ORDER BY r.request_id DESC
    LIMIT 10
");
$stmt->execute([$studentId]);
$requestHistory = $stmt->fetchAll();

// Get form history
$stmt = $pdo->prepare("
    SELECT f.*, u.full_name as hod_name
    FROM cost_share_forms f
    LEFT JOIN users u ON f.hod_approved_by = u.user_id
    WHERE f.student_id = ?
    ORDER BY f.form_id DESC
    LIMIT 10
");
$stmt->execute([$studentId]);
$formHistory = $stmt->fetchAll();

// Get current academic years
$academicYears = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1 OR status = 'active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Form - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .workflow-step {
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            position: relative;
        }
        .workflow-step .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            margin-right: 15px;
        }
        .workflow-step.active {
            background: #e8f0fe;
            border: 2px solid #1a73e8;
        }
        .workflow-step.completed {
            background: #e6f4ea;
            border: 2px solid #34a853;
        }
        .workflow-step.pending {
            background: #fef7e0;
            border: 2px solid #f9ab00;
        }
        .workflow-step .step-number.active { background: #1a73e8; color: white; }
        .workflow-step .step-number.completed { background: #34a853; color: white; }
        .workflow-step .step-number.pending { background: #f9ab00; color: white; }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-file-alt"></i> Cost-Share Form Request</h5>
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
        
        <!-- Workflow Status -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h6><i class="fas fa-route me-2"></i> Application Workflow</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="workflow-step <?php 
                            if ($pendingRequest) echo 'completed';
                            elseif ($formHistory) echo 'completed';
                            else echo 'active';
                        ?>">
                            <div class="d-flex align-items-center">
                                <div class="step-number <?php 
                                    if ($pendingRequest) echo 'completed';
                                    elseif ($formHistory) echo 'completed';
                                    else echo 'active';
                                ?>">1</div>
                                <div>
                                    <strong>Request Form</strong>
                                    <br>
                                    <small class="text-muted">Request form from HOD</small>
                                </div>
                            </div>
                            <?php if ($pendingRequest): ?>
                                <span class="badge bg-success mt-2">✓ Requested</span>
                            <?php elseif ($formHistory): ?>
                                <span class="badge bg-success mt-2">✓ Completed</span>
                            <?php else: ?>
                                <span class="badge bg-primary mt-2">Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="workflow-step <?php 
                            if ($pendingForm && !$pendingForm['status'] == 'pending') echo 'completed';
                            elseif ($pendingForm) echo 'active';
                            elseif ($formHistory) echo 'completed';
                            else echo '';
                        ?>">
                            <div class="d-flex align-items-center">
                                <div class="step-number <?php 
                                    if ($pendingForm && $pendingForm['status'] != 'pending') echo 'completed';
                                    elseif ($pendingForm) echo 'active';
                                    elseif ($formHistory) echo 'completed';
                                    else echo '';
                                ?>">2</div>
                                <div>
                                    <strong>Fill Form</strong>
                                    <br>
                                    <small class="text-muted">Complete the form</small>
                                </div>
                            </div>
                            <?php if ($pendingForm && $pendingForm['status'] == 'pending'): ?>
                                <span class="badge bg-warning mt-2">In Progress</span>
                            <?php elseif ($formHistory): ?>
                                <span class="badge bg-success mt-2">✓ Completed</span>
                            <?php else: ?>
                                <span class="badge bg-secondary mt-2">Waiting</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="workflow-step <?php 
                            if ($formHistory && $formHistory[0]['status'] == 'approved') echo 'completed';
                            elseif ($formHistory) echo 'active';
                            else echo '';
                        ?>">
                            <div class="d-flex align-items-center">
                                <div class="step-number <?php 
                                    if ($formHistory && $formHistory[0]['status'] == 'approved') echo 'completed';
                                    elseif ($formHistory) echo 'active';
                                    else echo '';
                                ?>">3</div>
                                <div>
                                    <strong>HOD Approval</strong>
                                    <br>
                                    <small class="text-muted">Awaiting HOD review</small>
                                </div>
                            </div>
                            <?php if ($formHistory && $formHistory[0]['status'] == 'approved'): ?>
                                <span class="badge bg-success mt-2">✓ Approved</span>
                            <?php elseif ($formHistory && $formHistory[0]['status'] == 'pending'): ?>
                                <span class="badge bg-warning mt-2">Pending</span>
                            <?php elseif ($formHistory && $formHistory[0]['status'] == 'rejected'): ?>
                                <span class="badge bg-danger mt-2">Rejected</span>
                            <?php else: ?>
                                <span class="badge bg-secondary mt-2">Waiting</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="workflow-step <?php 
                            if ($formHistory && $formHistory[0]['status'] == 'approved') echo 'active';
                            else echo '';
                        ?>">
                            <div class="d-flex align-items-center">
                                <div class="step-number <?php 
                                    if ($formHistory && $formHistory[0]['status'] == 'approved') echo 'active';
                                    else echo '';
                                ?>">4</div>
                                <div>
                                    <strong>Process</strong>
                                    <br>
                                    <small class="text-muted">Forwarded to Finance</small>
                                </div>
                            </div>
                            <?php if ($formHistory && $formHistory[0]['status'] == 'approved'): ?>
                                <span class="badge bg-info mt-2">Processing</span>
                            <?php else: ?>
                                <span class="badge bg-secondary mt-2">Waiting</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Step 1: Request Form -->
                <?php if (!$pendingRequest && !$formHistory): ?>
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6><i class="fas fa-paper-plane me-2"></i> Step 1: Request Form</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Request a cost-share form from your Department Head (HOD).
                            Once approved, you'll be able to fill and submit the form.
                        </p>
                        
                        <?php if ($hod): ?>
                            <form method="POST">
                                <?php echo Security::csrfField(); ?>
                                <input type="hidden" name="action" value="request_form">
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>HOD:</strong> <?php echo htmlspecialchars($hod['full_name']); ?>
                                    <br>
                                    <small class="text-muted">Your request will be sent to <?php echo htmlspecialchars($hod['full_name']); ?> for approval.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-paper-plane"></i> Request Form
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                No HOD assigned to your department. Please contact the administrator.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Step 2: Fill Form -->
                <?php if ($pendingForm && $pendingForm['status'] == 'pending'): ?>
                <div class="card">
                    <div class="card-header bg-warning">
                        <h6><i class="fas fa-pen-fancy me-2"></i> Step 2: Fill the Form</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Your form request has been approved by HOD. Please fill the form below.
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="submit_form">
                            
                            <div class="mb-3">
                                <label class="form-label">Academic Year *</label>
                                <select class="form-select" name="academic_year" required>
                                    <option value="">Select Academic Year</option>
                                    <?php foreach ($academicYears as $year): ?>
                                        <option value="<?php echo htmlspecialchars($year['year_code']); ?>">
                                            <?php echo htmlspecialchars($year['year_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Semester *</label>
                                <select class="form-select" name="semester" required>
                                    <option value="1">Semester 1</option>
                                    <option value="2">Semester 2</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Requested Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">ETB</span>
                                    <input type="number" class="form-control" name="requested_amount" 
                                           min="<?php echo MIN_COST_SHARE; ?>" 
                                           max="<?php echo MAX_COST_SHARE; ?>" step="100" required
                                           placeholder="Enter amount">
                                </div>
                                <small class="text-muted">Minimum: <?php echo formatCurrency(MIN_COST_SHARE); ?> | Maximum: <?php echo formatCurrency(MAX_COST_SHARE); ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Reason / Justification</label>
                                <textarea class="form-control" name="reason" rows="4" 
                                          placeholder="Explain why you need cost-share assistance"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-paper-plane"></i> Submit for HOD Approval
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Form History -->
                <?php if (!empty($formHistory)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h6><i class="fas fa-history me-2"></i> Form History</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Semester</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($formHistory as $form): ?>
                                    <tr>
                                        <td><?php echo formatDate($form['created_at']); ?></td>
                                        <td>
                                            <?php 
                                            $data = json_decode($form['form_data'], true);
                                            echo formatCurrency($data['requested_amount'] ?? 0);
                                            ?>
                                        </td>
                                        <td>Semester <?php echo $form['semester']; ?></td>
                                        <td>
                                            <?php if ($form['status'] == 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php elseif ($form['status'] == 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif ($form['status'] == 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php endif; ?>
                                            <?php if ($form['hod_comment']): ?>
                                                <br>
                                                <small class="text-muted">"<?php echo htmlspecialchars($form['hod_comment']); ?>"</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h6><i class="fas fa-info-circle me-2"></i> Your Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="text-muted">Name</label>
                            <div><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></div>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted">Student ID</label>
                            <div><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></div>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted">Department</label>
                            <div><strong><?php echo htmlspecialchars($student['dept_name']); ?></strong></div>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted">Program</label>
                            <div><strong><?php echo htmlspecialchars($student['program']); ?></strong></div>
                        </div>
                        <div class="mb-2">
                            <label class="text-muted">Year / Semester</label>
                            <div><strong>Year <?php echo $student['year_of_study']; ?> / Semester <?php echo $student['semester']; ?></strong></div>
                        </div>
                    </div>
                </div>
                
                <!-- Request History -->
                <?php if (!empty($requestHistory)): ?>
                <div class="card mt-3">
                    <div class="card-header bg-white">
                        <h6><i class="fas fa-history me-2"></i> Request History</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($requestHistory as $request): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <small class="text-muted"><?php echo formatDate($request['request_date']); ?></small>
                                <br>
                                <small>HOD: <?php echo htmlspecialchars($request['hod_name'] ?? 'N/A'); ?></small>
                            </div>
                            <div>
                                <?php if ($request['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php elseif ($request['status'] == 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($request['status'] == 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>