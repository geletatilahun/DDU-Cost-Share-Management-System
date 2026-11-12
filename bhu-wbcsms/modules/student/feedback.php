<?php
// ============================================
// STUDENT - FEEDBACK (ENHANCED)
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $subject = Security::sanitizeInput($_POST['subject']);
        $message_text = Security::sanitizeInput($_POST['message']);
        $category = $_POST['category'];
        
        if (empty($subject) || empty($message_text)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO feedback (student_id, subject, message, category, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$studentId, $subject, $message_text, $category]);
                
                $message = 'Feedback submitted successfully!';
                Security::logEvent($_SESSION['user_id'], 'SUBMIT_FEEDBACK', 'Submitted feedback: ' . $subject);
                
                // Send notification to admin
                $adminEmail = ADMIN_EMAIL ?? 'admin@bulehora.edu.et';
                sendEmail($adminEmail, 'New Feedback Received', "
                    <h3>New Feedback Received</h3>
                    <p><strong>From:</strong> {$user['full_name']}</p>
                    <p><strong>Subject:</strong> {$subject}</p>
                    <p><strong>Category:</strong> {$category}</p>
                    <p><strong>Message:</strong></p>
                    <p>{$message_text}</p>
                    <p><a href='" . SITE_URL . "modules/admin/feedback.php'>View Feedback</a></p>
                ");
                
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get student's feedback history
$stmt = $pdo->prepare("
    SELECT f.*, u.full_name as responder_name
    FROM feedback f
    LEFT JOIN users u ON f.responded_by = u.user_id
    WHERE f.student_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$studentId]);
$feedbackList = $stmt->fetchAll();

// Get stats
$totalFeedback = count($feedbackList);
$pendingFeedback = array_filter($feedbackList, function($f) { return $f['status'] == 'pending'; });
$respondedFeedback = array_filter($feedbackList, function($f) { return $f['status'] == 'responded'; });
$resolvedFeedback = array_filter($feedbackList, function($f) { return $f['status'] == 'resolved'; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .feedback-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #9b59b6;
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
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stats-card .number {
            font-size: 28px;
            font-weight: 700;
        }
        .stats-card .label {
            color: #7f8c8d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-comment"></i> Feedback</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                <i class="fas fa-plus"></i> New Feedback
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
                    <div class="number text-primary"><?php echo $totalFeedback; ?></div>
                    <div class="label">Total Feedback</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-warning"><?php echo count($pendingFeedback); ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-info"><?php echo count($respondedFeedback); ?></div>
                    <div class="label">Responded</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-success"><?php echo count($resolvedFeedback); ?></div>
                    <div class="label">Resolved</div>
                </div>
            </div>
        </div>
        
        <!-- Feedback List -->
        <?php if (empty($feedbackList)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-comment-dots fa-4x text-muted mb-3"></i>
                    <h5>No Feedback Yet</h5>
                    <p class="text-muted">You haven't submitted any feedback yet. Click "New Feedback" to share your thoughts.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($feedbackList as $item): ?>
            <div class="feedback-card <?php echo $item['status']; ?>">
                <div class="feedback-header">
                    <div>
                        <div class="subject"><?php echo htmlspecialchars($item['subject']); ?></div>
                        <div class="text-muted small">
                            <i class="fas fa-tag"></i> <?php echo ucfirst($item['category']); ?>
                            <span class="ms-3">
                                <i class="far fa-clock"></i> <?php echo timeAgo($item['created_at']); ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <?php if ($item['status'] == 'pending'): ?>
                            <span class="badge bg-warning">Pending</span>
                        <?php elseif ($item['status'] == 'read'): ?>
                            <span class="badge bg-info">Read</span>
                        <?php elseif ($item['status'] == 'responded'): ?>
                            <span class="badge bg-primary">Responded</span>
                        <?php elseif ($item['status'] == 'resolved'): ?>
                            <span class="badge bg-success">Resolved</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="feedback-body">
                    <p><?php echo nl2br(htmlspecialchars($item['message'])); ?></p>
                </div>
                
                <?php if ($item['response']): ?>
                <div class="feedback-response">
                    <strong><i class="fas fa-reply"></i> Response:</strong>
                    <p class="mt-2"><?php echo nl2br(htmlspecialchars($item['response'])); ?></p>
                    <small class="text-muted">
                        Responded by: <?php echo htmlspecialchars($item['responder_name'] ?? 'System'); ?>
                        on <?php echo formatDateTime($item['response_date']); ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-pen-fancy"></i> Submit Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject *</label>
                            <input type="text" class="form-control" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="general">General</option>
                                <option value="complaint">Complaint</option>
                                <option value="suggestion">Suggestion</option>
                                <option value="inquiry">Inquiry</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea class="form-control" name="message" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>