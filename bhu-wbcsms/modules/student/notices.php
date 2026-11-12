<?php
// ============================================
// STUDENT - NOTICES (ENHANCED)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_STUDENT);

$user = Auth::getCurrentUser();
$studentId = $user['student_id'] ?? null;
$deptId = $user['dept_id'] ?? null;

// Get notices targeted for students
$notices = $pdo->query("
    SELECT n.*, u.full_name as posted_by_name
    FROM notices n
    LEFT JOIN users u ON n.posted_by = u.user_id
    WHERE n.is_active = 1 
    AND (n.target_audience = 'all' OR n.target_audience = 'students')
    AND (n.dept_id IS NULL OR n.dept_id = " . (int)$deptId . ")
    AND (n.expiration_date IS NULL OR n.expiration_date >= CURDATE())
    ORDER BY n.created_at DESC
")->fetchAll();

// Get notice stats
$totalNotices = count($notices);
$latestNotices = array_slice($notices, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .notice-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #9b59b6;
            transition: all 0.3s;
        }
        .notice-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .notice-card .notice-title {
            font-weight: 700;
            font-size: 18px;
            color: #2C3E50;
        }
        .notice-card .notice-meta {
            font-size: 13px;
            color: #7f8c8d;
            margin: 8px 0;
        }
        .notice-card .notice-content {
            margin: 12px 0;
            color: #34495E;
        }
        .notice-card .notice-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .notice-type-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .notice-type-badge.general { background: #3498db; color: white; }
        .notice-type-badge.deadline { background: #f39c12; color: white; }
        .notice-type-badge.payment { background: #2ecc71; color: white; }
        .notice-type-badge.disbursement { background: #1abc9c; color: white; }
        .notice-type-badge.alert { background: #e74c3c; color: white; }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stats-card .number {
            font-size: 32px;
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
            <h5><i class="fas fa-bullhorn"></i> Notices</h5>
        </div>
        
        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number text-primary"><?php echo $totalNotices; ?></div>
                    <div class="label">Total Notices</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number text-success"><?php echo count($latestNotices); ?></div>
                    <div class="label">Latest Notices</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="number text-info">
                        <?php 
                        $types = array_unique(array_column($notices, 'notice_type'));
                        echo count($types);
                        ?>
                    </div>
                    <div class="label">Notice Types</div>
                </div>
            </div>
        </div>
        
        <!-- Notices List -->
        <?php if (empty($notices)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                    <h5>No Notices Available</h5>
                    <p class="text-muted">There are no notices at this time. Please check back later.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($notices as $notice): ?>
            <div class="notice-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></div>
                        <div class="notice-meta">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($notice['posted_by_name'] ?? 'System'); ?>
                            <span class="ms-3">
                                <i class="far fa-clock"></i> 
                                <?php echo timeAgo($notice['created_at']); ?>
                            </span>
                            <?php if ($notice['expiration_date']): ?>
                                <span class="ms-3">
                                    <i class="fas fa-hourglass-end"></i> 
                                    Expires: <?php echo formatDate($notice['expiration_date']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notice-badges">
                        <span class="notice-type-badge <?php echo $notice['notice_type']; ?>">
                            <?php echo ucfirst($notice['notice_type']); ?>
                        </span>
                        <span class="badge bg-info"><?php echo ucfirst($notice['target_audience']); ?></span>
                    </div>
                </div>
                
                <div class="notice-content">
                    <?php echo nl2br(htmlspecialchars($notice['content'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>