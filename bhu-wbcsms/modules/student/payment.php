<?php
// ============================================
// STUDENT - PAYMENT (FIXED)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_STUDENT);

$user = Auth::getCurrentUser();
$studentId = $user['student_id'] ?? null;

if (!$studentId) {
    // Try to find student by user_id
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();
    if ($student) {
        $studentId = $student['student_id'];
        $stmt = $pdo->prepare("UPDATE users SET student_id = ? WHERE user_id = ?");
        $stmt->execute([$studentId, $_SESSION['user_id']]);
    } else {
        die('Student record not found. Please contact administrator.');
    }
}

$message = '';
$error = '';

// Get student info
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    die('Student record not found.');
}

// Get or create cost-share record
$stmt = $pdo->prepare("
    SELECT * FROM cost_share_records 
    WHERE student_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute([$studentId]);
$record = $stmt->fetch();

// If no record exists, create one
if (!$record) {
    // Get current academic year
    $currentYear = $pdo->query("SELECT ac_year_id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch();
    $acYearId = $currentYear ? $currentYear['ac_year_id'] : null;
    
    if ($acYearId) {
        // Create a default cost-share record
        $stmt = $pdo->prepare("
            INSERT INTO cost_share_records (
                student_id, ac_year_id, semester, total_cost_share, 
                student_share, university_share, payment_status
            ) VALUES (?, ?, ?, 0, 0, 0, 'pending')
        ");
        $stmt->execute([
            $studentId,
            $acYearId,
            $student['semester'] ?? 1
        ]);
        
        // Get the newly created record
        $recordId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM cost_share_records WHERE record_id = ?");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
    }
}

// Get payments
$stmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE student_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$studentId]);
$payments = $stmt->fetchAll();

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $amount = (float)$_POST['amount'];
        $paymentMethod = $_POST['payment_method'];
        $transactionId = Security::sanitizeInput($_POST['transaction_id']);
        $paymentDate = $_POST['payment_date'];
        
        if ($amount <= 0) {
            $error = 'Please enter a valid amount.';
        } elseif (!$record) {
            $error = 'No cost-share record found. Please contact the Cost-Share Officer.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Upload payment proof
                $proofPath = null;
                if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === 0) {
                    $uploadResult = uploadFile($_FILES['payment_proof'], '../../uploads/payments/');
                    if ($uploadResult['success']) {
                        $proofPath = $uploadResult['filename'];
                    } else {
                        $error = 'Proof upload failed: ' . $uploadResult['message'];
                    }
                } else {
                    $error = 'Please upload a payment proof.';
                }
                
                if (!$error) {
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (
                            student_id, record_id, amount, payment_date, 
                            payment_method, transaction_id, payment_proof, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $studentId,
                        $record['record_id'],
                        $amount,
                        $paymentDate,
                        $paymentMethod,
                        $transactionId,
                        $proofPath
                    ]);
                    
                    // Update cost_share_records payment status
                    $stmt = $pdo->prepare("
                        UPDATE cost_share_records 
                        SET payment_status = 'partial',
                            student_payment = student_payment + ?
                        WHERE record_id = ?
                    ");
                    $stmt->execute([$amount, $record['record_id']]);
                    
                    $pdo->commit();
                    
                    $message = 'Payment submitted for verification!';
                    Security::logEvent($_SESSION['user_id'], 'SUBMIT_PAYMENT', 
                                      "Submitted payment of " . formatCurrency($amount));
                    
                    // Notify finance
                    $financeUsers = $pdo->query("SELECT email FROM users WHERE role_id = 4 AND is_active = 1")->fetchAll();
                    foreach ($financeUsers as $finance) {
                        sendEmail($finance['email'], 'New Payment Submitted', "
                            <h3>New Payment Submitted</h3>
                            <p><strong>Student:</strong> {$student['full_name']}</p>
                            <p><strong>Student ID:</strong> {$student['student_code']}</p>
                            <p><strong>Amount:</strong> " . formatCurrency($amount) . "</p>
                            <p><a href='" . SITE_URL . "modules/finance/payments.php'>Verify Payment</a></p>
                        ");
                    }
                    
                    // Refresh payments
                    $stmt = $pdo->prepare("
                        SELECT * FROM payments 
                        WHERE student_id = ? 
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$studentId]);
                    $payments = $stmt->fetchAll();
                    
                    // Refresh record
                    $stmt = $pdo->prepare("
                        SELECT * FROM cost_share_records 
                        WHERE record_id = ?
                    ");
                    $stmt->execute([$record['record_id']]);
                    $record = $stmt->fetch();
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Calculate remaining balance
$remainingBalance = 0;
if ($record) {
    $remainingBalance = $record['student_share'] - ($record['student_payment'] ?? 0);
    if ($remainingBalance < 0) $remainingBalance = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .amount-due {
            font-size: 32px;
            font-weight: 700;
            color: #2C3E50;
        }
        .balance-positive {
            color: #28a745;
        }
        .balance-negative {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include '../../includes/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-credit-card"></i> Make Payment</h5>
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
            <!-- Payment Form -->
            <div class="col-md-7">
                <div class="payment-card">
                    <h6 class="text-muted mb-3"><i class="fas fa-money-bill-wave"></i> Submit Payment</h6>
                    
                    <?php if (!$record): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>No cost-share record found.</strong><br>
                            Please contact the Cost-Share Officer to set up your cost-share record.
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <?php echo Security::csrfField(); ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">ETB</span>
                                    <input type="number" class="form-control" name="amount" 
                                           min="100" step="100" required 
                                           placeholder="Enter amount"
                                           value="<?php echo $remainingBalance > 0 ? min($remainingBalance, $record['student_share']) : ''; ?>">
                                </div>
                                <?php if ($remainingBalance > 0): ?>
                                    <small class="text-muted">Remaining balance: <?php echo formatCurrency($remainingBalance); ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Payment Method *</label>
                                        <select class="form-select" name="payment_method" required>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="cash">Cash</option>
                                            <option value="check">Check</option>
                                            <option value="online">Online Payment</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Payment Date *</label>
                                        <input type="date" class="form-control" name="payment_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Transaction ID</label>
                                <input type="text" class="form-control" name="transaction_id" 
                                       placeholder="Enter transaction reference number">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Payment Proof *</label>
                                <input type="file" class="form-control" name="payment_proof" 
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                                <small class="text-muted">Upload bank receipt or payment confirmation (PDF, JPG, PNG)</small>
                            </div>
                            
                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-check"></i> Submit Payment
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment Summary -->
            <div class="col-md-5">
                <div class="payment-card">
                    <h6 class="text-muted mb-3"><i class="fas fa-info-circle"></i> Payment Summary</h6>
                    
                    <?php if ($record): ?>
                        <div class="mb-3">
                            <label class="text-muted">Total Cost-Share</label>
                            <div class="amount-due"><?php echo formatCurrency($record['total_cost_share']); ?></div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <label class="text-muted">Student Share</label>
                                <div><strong><?php echo formatCurrency($record['student_share']); ?></strong></div>
                            </div>
                            <div class="col-6">
                                <label class="text-muted">University Share</label>
                                <div><strong><?php echo formatCurrency($record['university_share']); ?></strong></div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label class="text-muted">Payment Status</label>
                            <div><?php echo getStatusBadge($record['payment_status']); ?></div>
                        </div>
                        
                        <?php if (($record['student_payment'] ?? 0) > 0): ?>
                            <div class="mb-3">
                                <label class="text-muted">Already Paid</label>
                                <div><strong class="text-success"><?php echo formatCurrency($record['student_payment']); ?></strong></div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="text-muted">Remaining Balance</label>
                                <div>
                                    <strong class="<?php echo $remainingBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo formatCurrency($remainingBalance); ?>
                                    </strong>
                                    <?php if ($remainingBalance <= 0): ?>
                                        <span class="badge bg-success">Fully Paid</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($remainingBalance <= 0 && $record['student_payment'] > 0): ?>
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle"></i> 
                                Your student share is fully paid!
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No cost-share record found.</p>
                            <p class="small">Please contact the Cost-Share Officer.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Payments -->
                <?php if (!empty($payments)): ?>
                <div class="payment-card">
                    <h6 class="text-muted mb-3"><i class="fas fa-history"></i> Recent Payments</h6>
                    <?php foreach ($payments as $payment): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong><?php echo formatCurrency($payment['amount']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo formatDate($payment['payment_date']); ?></small>
                        </div>
                        <div>
                            <?php if ($payment['status'] == 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php elseif ($payment['status'] == 'verified'): ?>
                                <span class="badge bg-success">Verified</span>
                            <?php elseif ($payment['status'] == 'rejected'): ?>
                                <span class="badge bg-danger">Rejected</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>