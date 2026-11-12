<?php
// ============================================
// STUDENT - APPLY FOR COST-SHARE
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_STUDENT);

$user = Auth::getCurrentUser();
$studentId = $user['student_id'] ?? null;

if (!$studentId) {
    die('Student record not found. Please contact administrator.');
}

$message = '';
$error = '';

// Get student info
$stmt = $pdo->prepare("
    SELECT s.*, d.dept_name 
    FROM students s
    JOIN departments d ON s.department_id = d.dept_id
    WHERE s.student_id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

// Get current academic year
$currentYear = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1")->fetch();

// Check if student already has an application for current semester
$stmt = $pdo->prepare("
    SELECT * FROM cost_share_applications 
    WHERE student_id = ? AND ac_year_id = ? AND semester = ?
    AND status NOT IN ('rejected', 'disbursed')
");
$stmt->execute([$studentId, $currentYear['ac_year_id'] ?? 0, $student['semester'] ?? 1]);
$existingApplication = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $requestedAmount = (float)$_POST['requested_amount'];
        $reason = Security::sanitizeInput($_POST['reason']);
        
        if ($requestedAmount < MIN_COST_SHARE) {
            $error = 'Requested amount must be at least ' . formatCurrency(MIN_COST_SHARE);
        } elseif ($requestedAmount > MAX_COST_SHARE) {
            $error = 'Requested amount cannot exceed ' . formatCurrency(MAX_COST_SHARE);
        } else {
            try {
                $documentPath = null;
                if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
                    $uploadResult = uploadFile($_FILES['document'], '../../uploads/documents/');
                    if ($uploadResult['success']) {
                        $documentPath = $uploadResult['filename'];
                    } else {
                        $error = 'Document upload failed: ' . $uploadResult['message'];
                    }
                }
                
                if (!$error) {
                    $stmt = $pdo->prepare("
                        INSERT INTO cost_share_applications (
                            student_id, ac_year_id, semester, application_date,
                            requested_amount, reason, supporting_documents, status
                        ) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $studentId,
                        $currentYear['ac_year_id'],
                        $student['semester'],
                        $requestedAmount,
                        $reason,
                        $documentPath
                    ]);
                    
                    $message = 'Application submitted successfully!';
                    Security::logEvent($_SESSION['user_id'], 'APPLY_COST_SHARE', 
                                      "Applied for cost-share: " . formatCurrency($requestedAmount));
                    
                    // Send notification to officer
                    $officers = $pdo->query("SELECT email FROM users WHERE role_id = 2 AND is_active = 1")->fetchAll();
                    foreach ($officers as $officer) {
                        sendEmail($officer['email'], 'New Cost-Share Application', "
                            <h3>New Application Received</h3>
                            <p><strong>Student:</strong> {$student['full_name']}</p>
                            <p><strong>Student ID:</strong> {$student['student_code']}</p>
                            <p><strong>Amount:</strong> " . formatCurrency($requestedAmount) . "</p>
                            <p><strong>Department:</strong> {$student['dept_name']}</p>
                            <p><a href='" . SITE_URL . "modules/officer/applications.php'>View Application</a></p>
                        ");
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .apply-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .info-box label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 13px;
            text-transform: uppercase;
        }
        .info-box p {
            font-size: 18px;
            font-weight: 600;
            margin: 5px 0 0 0;
            color: #2C3E50;
        }
    </style>
</head>
<body>
    <?php include '../../includes/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-pen-fancy"></i> Apply for Cost-Share</h5>
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
        
        <div class="row">
            <!-- Student Info -->
            <div class="col-md-4">
                <div class="apply-card">
                    <h6 class="text-muted mb-3"><i class="fas fa-user-graduate"></i> Your Information</h6>
                    <div class="info-box">
                        <label>Student Name</label>
                        <p><?php echo htmlspecialchars($student['full_name']); ?></p>
                    </div>
                    <div class="info-box">
                        <label>Student ID</label>
                        <p><?php echo htmlspecialchars($student['student_code']); ?></p>
                    </div>
                    <div class="info-box">
                        <label>Program</label>
                        <p><?php echo htmlspecialchars($student['program']); ?></p>
                    </div>
                    <div class="info-box">
                        <label>Department</label>
                        <p><?php echo htmlspecialchars($student['dept_name']); ?></p>
                    </div>
                    <div class="info-box">
                        <label>Year / Semester</label>
                        <p>Year <?php echo $student['year_of_study']; ?> / Semester <?php echo $student['semester']; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Application Form -->
            <div class="col-md-8">
                <div class="apply-card">
                    <h6 class="text-muted mb-3"><i class="fas fa-file-alt"></i> Application Form</h6>
                    
                    <?php if ($existingApplication): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-clock"></i> You already have a pending application for this semester. 
                            Please wait for it to be processed.
                            <br>
                            <strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $existingApplication['status'])); ?>
                            <br>
                            <a href="status.php" class="btn btn-sm btn-primary mt-2">Check Status</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <?php echo Security::csrfField(); ?>
                            
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
                            
                            <div class="mb-3">
                                <label class="form-label">Supporting Document</label>
                                <input type="file" class="form-control" name="document" 
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <small class="text-muted">Accepted: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-paper-plane"></i> Submit Application
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>