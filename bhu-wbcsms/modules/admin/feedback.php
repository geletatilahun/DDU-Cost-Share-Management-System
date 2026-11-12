<?php
// ============================================
// ADMIN - FEEDBACK MANAGEMENT
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_ADMIN);

$message = '';
$error = '';
$viewFeedback = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // UPDATE FEEDBACK STATUS
        if ($action === 'update_status') {
            $feedbackId = (int)$_POST['feedback_id'];
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("UPDATE feedback SET status = ? WHERE feedback_id = ?");
            $stmt->execute([$status, $feedbackId]);
            $message = 'Feedback status updated!';
            Security::logEvent($_SESSION['user_id'], 'UPDATE_FEEDBACK', "Updated feedback ID: {$feedbackId}");
        }
        
        // RESPOND TO FEEDBACK
        elseif ($action === 'respond') {
            $feedbackId = (int)$_POST['feedback_id'];
            $response = Security::sanitizeInput($_POST['response']);
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE feedback 
                    SET response = ?, responded_by = ?, response_date = NOW(), status = 'responded'
                    WHERE feedback_id = ?
                ");
                $stmt->execute([$response, $_SESSION['user_id'], $feedbackId]);
                
                $message = 'Response sent successfully!';
                Security::logEvent($_SESSION['user_id'], 'RESPOND_FEEDBACK', "Responded to feedback ID: {$feedbackId}");
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // DELETE FEEDBACK
        elseif ($action === 'delete') {
            $feedbackId = (int)$_POST['feedback_id'];
            $stmt = $pdo->prepare("DELETE FROM feedback WHERE feedback_id = ?");
            $stmt->execute([$feedbackId]);
            $message = 'Feedback deleted successfully!';
            Security::logEvent($_SESSION['user_id'], 'DELETE_FEEDBACK', "Deleted feedback ID: {$feedbackId}");
        }
    }
}

// View single feedback
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("
        SELECT f.*, u.full_name as student_name, u.email as student_email,
               r.full_name as responder_name
        FROM feedback f
        LEFT JOIN students s ON f.student_id = s.student_id
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN users r ON f.responded_by = r.user_id
        WHERE f.feedback_id = ?
    ");
    $stmt->execute([$_GET['view']]);
    $viewFeedback = $stmt->fetch();
}

// Get all feedback
$feedback = $pdo->query("
    SELECT f.*, 
           u.full_name as student_name,
           u.email as student_email,
           r.full_name as responder_name,
           s.student_code
    FROM feedback f
    LEFT JOIN students s ON f.student_id = s.student_id
    LEFT JOIN users u ON s.user_id = u.user_id
    LEFT JOIN users r ON f.responded_by = r.user_id
    ORDER BY f.created_at DESC
")->fetchAll();

// Get stats
$totalFeedback = count($feedback);
$pendingFeedback = array_filter($feedback, function($f) { return $f['status'] == 'pending'; });
$respondedFeedback = array_filter($feedback, function($f) { return $f['status'] == 'responded'; });
$resolvedFeedback = array_filter($feedback, function($f) { return $f['status'] == 'resolved'; });

// Get categories
$categories = $pdo->query("
    SELECT category, COUNT(*) as count 
    FROM feedback 
    GROUP BY category
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .feedback-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        .feedback-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .feedback-card.pending {
            border-left-color: #f39c12;
        }
        .feedback-card.responded {
            border-left-color: #3498db;
        }
        .feedback-card.resolved {
            border-left-color: #2ecc71;
        }
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .feedback-header .subject {
            font-weight: 700;
            font-size: 16px;
        }
        .feedback-header .student {
            color: #7f8c8d;
            font-size: 14px;
        }
        .feedback-body {
            margin: 10px 0;
        }
        .feedback-response {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
            border-left: 3px solid #3498db;
        }
    </style>
</head>
<body>
    <?php include '../../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-comment"></i> Feedback Management</h5>
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
                    <div class="icon"><i class="fas fa-comments"></i></div>
                    <div class="number text-primary"><?php echo $totalFeedback; ?></div>
                    <div class="label">Total Feedback</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="number text-warning"><?php echo count($pendingFeedback); ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-reply"></i></div>
                    <div class="number text-info"><?php echo count($respondedFeedback); ?></div>
                    <div class="label">Responded</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="number text-success"><?php echo count($resolvedFeedback); ?></div>
                    <div class="label">Resolved</div>
                </div>
            </div>
        </div>
        
        <!-- Categories -->
        <div class="row g-4 mb-4">
            <?php foreach ($categories as $cat): ?>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-tag"></i></div>
                    <div class="number text-<?php 
                        echo $cat['category'] == 'general' ? 'primary' : 
                            ($cat['category'] == 'complaint' ? 'danger' : 
                            ($cat['category'] == 'suggestion' ? 'success' : 'info')); 
                    ?>">
                        <?php echo $cat['count']; ?>
                    </div>
                    <div class="label"><?php echo ucfirst($cat['category']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Feedback Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="feedbackTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedback as $item): ?>
                        <tr>
                            <td>#<?php echo $item['feedback_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($item['student_name'] ?? 'Unknown'); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($item['student_code'] ?? ''); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($item['subject']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $item['category'] == 'general' ? 'primary' : 
                                        ($item['category'] == 'complaint' ? 'danger' : 
                                        ($item['category'] == 'suggestion' ? 'success' : 'info')); 
                                ?>">
                                    <?php echo ucfirst($item['category']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($item['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php elseif ($item['status'] == 'read'): ?>
                                    <span class="badge bg-info">Read</span>
                                <?php elseif ($item['status'] == 'responded'): ?>
                                    <span class="badge bg-primary">Responded</span>
                                <?php elseif ($item['status'] == 'resolved'): ?>
                                    <span class="badge bg-success">Resolved</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDateTime($item['created_at']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?view=<?php echo $item['feedback_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn btn-outline-danger" onclick="deleteFeedback(<?php echo $item['feedback_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- View Feedback Modal -->
    <?php if ($viewFeedback): ?>
    <div class="modal fade show" id="viewFeedbackModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-comment"></i> Feedback Details</h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <div class="feedback-card <?php echo $viewFeedback['status']; ?>">
                        <div class="feedback-header">
                            <div>
                                <div class="subject"><?php echo htmlspecialchars($viewFeedback['subject']); ?></div>
                                <div class="student">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($viewFeedback['student_name'] ?? 'Unknown'); ?>
                                    <span class="ms-3">
                                        <i class="fas fa-envelope"></i> 
                                        <?php echo htmlspecialchars($viewFeedback['student_email'] ?? 'N/A'); ?>
                                    </span>
                                    <span class="ms-3">
                                        <i class="fas fa-tag"></i> 
                                        <?php echo ucfirst($viewFeedback['category']); ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <span class="badge bg-<?php 
                                    echo $viewFeedback['status'] == 'pending' ? 'warning' : 
                                        ($viewFeedback['status'] == 'responded' ? 'primary' : 'success'); 
                                ?>">
                                    <?php echo ucfirst($viewFeedback['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="feedback-body">
                            <p><?php echo nl2br(htmlspecialchars($viewFeedback['message'])); ?></p>
                            <small class="text-muted">
                                <i class="far fa-clock"></i> 
                                <?php echo formatDateTime($viewFeedback['created_at']); ?>
                            </small>
                        </div>
                        
                        <?php if ($viewFeedback['response']): ?>
                        <div class="feedback-response">
                            <strong><i class="fas fa-reply"></i> Response:</strong>
                            <p class="mt-2"><?php echo nl2br(htmlspecialchars($viewFeedback['response'])); ?></p>
                            <small class="text-muted">
                                Responded by: <?php echo htmlspecialchars($viewFeedback['responder_name'] ?? 'System'); ?>
                                on <?php echo formatDateTime($viewFeedback['response_date']); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($viewFeedback['status'] != 'resolved'): ?>
                    <form method="POST" class="mt-3">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="respond">
                        <input type="hidden" name="feedback_id" value="<?php echo $viewFeedback['feedback_id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Your Response</label>
                            <textarea class="form-control" name="response" rows="4" required 
                                      placeholder="Enter your response to this feedback..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-reply"></i> Send Response
                                </button>
                            </div>
                            <div class="col-md-6 text-end">
                                <form method="POST" class="d-inline">
                                    <?php echo Security::csrfField(); ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="feedback_id" value="<?php echo $viewFeedback['feedback_id']; ?>">
                                    <input type="hidden" name="status" value="resolved">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> Mark as Resolved
                                    </button>
                                </form>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="?" class="btn btn-secondary">Close</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#feedbackTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search feedback:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ feedback"
                }
            });
        });
        
        function deleteFeedback(feedbackId) {
            if (confirm('Are you sure you want to delete this feedback?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="feedback_id" value="${feedbackId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>