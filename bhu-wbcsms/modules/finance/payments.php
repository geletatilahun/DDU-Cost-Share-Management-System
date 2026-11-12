<?php
// ============================================
// FINANCE - PAYMENT VERIFICATION
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_FINANCE);

$message = '';
$error = '';
$viewPayment = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // VERIFY PAYMENT
        if ($action === 'verify') {
            $paymentId = (int)$_POST['payment_id'];
            $comment = Security::sanitizeInput($_POST['comment'] ?? '');
            
            try {
                $pdo->beginTransaction();
                
                // Update payment status
                $stmt = $pdo->prepare("
                    UPDATE payments 
                    SET status = 'verified', 
                        verified_by = ?,
                        verification_date = NOW(),
                        verification_comment = ?
                    WHERE payment_id = ? AND status = 'pending'
                ");
                $stmt->execute([$_SESSION['user_id'], $comment, $paymentId]);
                
                if ($stmt->rowCount() > 0) {
                    // Get payment details
                    $stmt = $pdo->prepare("
                        SELECT p.*, s.full_name, s.email, s.student_code 
                        FROM payments p
                        JOIN students s ON p.student_id = s.student_id
                        WHERE p.payment_id = ?
                    ");
                    $stmt->execute([$paymentId]);
                    $payment = $stmt->fetch();
                    
                    // Update cost_share_records
                    $stmt = $pdo->prepare("
                        UPDATE cost_share_records 
                        SET payment_status = 'verified',
                            student_payment = student_payment + ?,
                            proof_verified = 1,
                            verified_by = ?,
                            verification_date = NOW()
                        WHERE record_id = ?
                    ");
                    $stmt->execute([$payment['amount'], $_SESSION['user_id'], $payment['record_id']]);
                    
                    $pdo->commit();
                    
                    $message = 'Payment verified successfully!';
                    Security::logEvent($_SESSION['user_id'], 'VERIFY_PAYMENT', 
                                      "Verified payment #{$paymentId} for {$payment['full_name']}");
                    
                    // Send notification
                    sendEmail($payment['email'], 'Payment Verified', "
                        <h3>Payment Verified</h3>
                        <p>Dear {$payment['full_name']},</p>
                        <p>Your payment of <strong>" . formatCurrency($payment['amount']) . "</strong> has been verified.</p>
                        <p>Your payment status is now confirmed.</p>
                    ");
                } else {
                    $error = 'Payment not found or already verified.';
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // REJECT PAYMENT
        elseif ($action === 'reject') {
            $paymentId = (int)$_POST['payment_id'];
            $reason = Security::sanitizeInput($_POST['reject_reason']);
            
            if (empty($reason)) {
                $error = 'Please provide a reason for rejection.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("
                        UPDATE payments 
                        SET status = 'rejected', 
                            verified_by = ?,
                            verification_date = NOW(),
                            verification_comment = ?
                        WHERE payment_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$_SESSION['user_id'], $reason, $paymentId]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Get payment details
                        $stmt = $pdo->prepare("
                            SELECT p.*, s.full_name, s.email 
                            FROM payments p
                            JOIN students s ON p.student_id = s.student_id
                            WHERE p.payment_id = ?
                        ");
                        $stmt->execute([$paymentId]);
                        $payment = $stmt->fetch();
                        
                        $pdo->commit();
                        
                        $message = 'Payment rejected.';
                        Security::logEvent($_SESSION['user_id'], 'REJECT_PAYMENT', 
                                          "Rejected payment #{$paymentId}");
                        
                        // Send notification
                        sendEmail($payment['email'], 'Payment Rejected', "
                            <h3>Payment Rejected</h3>
                            <p>Dear {$payment['full_name']},</p>
                            <p>Your payment of <strong>" . formatCurrency($payment['amount']) . "</strong> has been rejected.</p>
                            <p><strong>Reason:</strong> {$reason}</p>
                            <p>Please upload a valid payment proof or contact the Finance Department.</p>
                        ");
                    } else {
                        $error = 'Payment not found or already processed.';
                    }
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// View single payment
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("
        SELECT p.*, s.full_name as student_name, s.student_code, s.email as student_email,
               u.full_name as verified_by_name
        FROM payments p
        JOIN students s ON p.student_id = s.student_id
        LEFT JOIN users u ON p.verified_by = u.user_id
        WHERE p.payment_id = ?
    ");
    $stmt->execute([$_GET['view']]);
    $viewPayment = $stmt->fetch();
}

// Get payments with filters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';

$query = "
    SELECT p.*, s.full_name as student_name, s.student_code,
           u.full_name as verified_by_name
    FROM payments p
    JOIN students s ON p.student_id = s.student_id
    LEFT JOIN users u ON p.verified_by = u.user_id
    WHERE 1=1
";

$params = [];

if ($statusFilter != 'all') {
    $query .= " AND p.status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter) {
    $query .= " AND DATE(p.payment_date) = ?";
    $params[] = $dateFilter;
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get statistics
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
$stats['pending'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$stats['verified'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'")->fetchColumn();
$stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'rejected'")->fetchColumn();
$stats['total_amount'] = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - <?php echo SITE_NAME; ?></title>
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
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .proof-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 5px;
            cursor: pointer;
        }
        .payment-detail {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .payment-detail .label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 13px;
            text-transform: uppercase;
        }
        .payment-detail .value {
            font-size: 16px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/finance_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-credit-card"></i> Payment Verification</h5>
            <div>
                <span class="badge bg-warning me-2">
                    <i class="fas fa-clock"></i> <?php echo $stats['pending']; ?> Pending
                </span>
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
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-file-invoice"></i></div>
                    <div class="number text-primary"><?php echo $stats['total']; ?></div>
                    <div class="label">Total Payments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="number text-warning"><?php echo $stats['pending']; ?></div>
                    <div class="label">Pending Verification</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="number text-success"><?php echo $stats['verified']; ?></div>
                    <div class="label">Verified</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="number text-info"><?php echo formatCurrency($stats['total_amount']); ?></div>
                    <div class="label">Total Verified Amount</div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all">All Status</option>
                        <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="verified" <?php echo $statusFilter == 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="rejected" <?php echo $statusFilter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo $dateFilter; ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="payments.php" class="btn btn-secondary w-100 mt-2">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Payments Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="paymentTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Proof</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>#<?php echo $payment['payment_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['student_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($payment['student_code']); ?></small>
                            </td>
                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                            <td><?php echo ucfirst($payment['payment_method']); ?></td>
                            <td><?php echo formatDate($payment['payment_date']); ?></td>
                            <td>
                                <?php if ($payment['payment_proof']): ?>
                                    <a href="../../uploads/payments/<?php echo $payment['payment_proof']; ?>" target="_blank">
                                        <i class="fas fa-file-pdf text-danger"></i> View
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">No proof</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($payment['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php elseif ($payment['status'] == 'verified'): ?>
                                    <span class="badge bg-success">Verified</span>
                                <?php elseif ($payment['status'] == 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?view=<?php echo $payment['payment_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($payment['status'] == 'pending'): ?>
                                        <button class="btn btn-outline-success" onclick="openVerifyModal(<?php echo $payment['payment_id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="openRejectModal(<?php echo $payment['payment_id']; ?>)">
                                            <i class="fas fa-times"></i>
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
    
    <!-- View Payment Modal -->
    <?php if ($viewPayment): ?>
    <div class="modal fade show" id="viewPaymentModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-credit-card"></i> Payment #<?php echo $viewPayment['payment_id']; ?></h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="payment-detail">
                                <div class="label">Student</div>
                                <div class="value"><?php echo htmlspecialchars($viewPayment['student_name']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="payment-detail">
                                <div class="label">Student ID</div>
                                <div class="value"><?php echo htmlspecialchars($viewPayment['student_code']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="payment-detail">
                                <div class="label">Amount</div>
                                <div class="value text-primary">
                                    <strong><?php echo formatCurrency($viewPayment['amount']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="payment-detail">
                                <div class="label">Payment Method</div>
                                <div class="value"><?php echo ucfirst($viewPayment['payment_method']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="payment-detail">
                                <div class="label">Transaction ID</div>
                                <div class="value"><?php echo htmlspecialchars($viewPayment['transaction_id'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="payment-detail">
                                <div class="label">Payment Date</div>
                                <div class="value"><?php echo formatDate($viewPayment['payment_date']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($viewPayment['payment_proof']): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="payment-detail">
                                <div class="label">Payment Proof</div>
                                <div class="value">
                                    <a href="../../uploads/payments/<?php echo $viewPayment['payment_proof']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="fas fa-file-pdf"></i> View Document
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="payment-detail">
                                <div class="label">Status</div>
                                <div class="value">
                                    <?php if ($viewPayment['status'] == 'pending'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php elseif ($viewPayment['status'] == 'verified'): ?>
                                        <span class="badge bg-success">Verified</span>
                                    <?php elseif ($viewPayment['status'] == 'rejected'): ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="payment-detail">
                                <div class="label">Verified By</div>
                                <div class="value"><?php echo htmlspecialchars($viewPayment['verified_by_name'] ?? 'Not verified'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($viewPayment['verification_comment']): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="payment-detail">
                                <div class="label">Comment</div>
                                <div class="value"><?php echo htmlspecialchars($viewPayment['verification_comment']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($viewPayment['status'] == 'pending'): ?>
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> This payment is pending verification.
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success" onclick="openVerifyModal(<?php echo $viewPayment['payment_id']; ?>)">
                                <i class="fas fa-check"></i> Verify
                            </button>
                            <button class="btn btn-danger" onclick="openRejectModal(<?php echo $viewPayment['payment_id']; ?>)">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <a href="?" class="btn btn-secondary">Close</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Verify Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Verify Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="verify">
                        <input type="hidden" name="payment_id" id="verify_payment_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Comment (Optional)</label>
                            <textarea class="form-control" name="comment" rows="3" 
                                      placeholder="Add any comments about this verification"></textarea>
                        </div>
                        
                        <div class="alert alert-success">
                            <i class="fas fa-info-circle"></i> 
                            This will mark the payment as verified and update the student's payment status.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Verify Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="payment_id" id="reject_payment_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection *</label>
                            <textarea class="form-control" name="reject_reason" rows="4" required
                                      placeholder="Please provide a detailed reason for rejection"></textarea>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            This action cannot be undone. The student will be notified.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Payment</button>
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
            $('#paymentTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search payments:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ payments"
                }
            });
        });
        
        function openVerifyModal(paymentId) {
            document.getElementById('verify_payment_id').value = paymentId;
            new bootstrap.Modal(document.getElementById('verifyModal')).show();
        }
        
        function openRejectModal(paymentId) {
            document.getElementById('reject_payment_id').value = paymentId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
    </script>
</body>
</html>