<?php
// ============================================
// STUDENT - MINIMAL DASHBOARD (TEST)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole(ROLE_STUDENT);

$user = Auth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-chart-pie"></i> Student Dashboard</h4>
                    </div>
                    <div class="card-body">
                        <h5>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h5>
                        <p>User ID: <?php echo $_SESSION['user_id']; ?></p>
                        <p>Student ID: <?php echo $_SESSION['student_id'] ?? 'Not set'; ?></p>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="fas fa-file-alt fa-3x text-primary"></i>
                                        <h3>0</h3>
                                        <p>Total Applications</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clock fa-3x text-warning"></i>
                                        <h3>0</h3>
                                        <p>Pending</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="fas fa-check-circle fa-3x text-success"></i>
                                        <h3>0</h3>
                                        <p>Approved</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <a href="apply.php" class="btn btn-primary w-100">Apply Now</a>
                            </div>
                            <div class="col-md-3">
                                <a href="status.php" class="btn btn-success w-100">Check Status</a>
                            </div>
                            <div class="col-md-3">
                                <a href="payment.php" class="btn btn-warning w-100">Make Payment</a>
                            </div>
                            <div class="col-md-3">
                                <a href="../../logout.php" class="btn btn-danger w-100">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>