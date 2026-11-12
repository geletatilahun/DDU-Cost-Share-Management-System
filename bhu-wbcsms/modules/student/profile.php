<?php
// ============================================
// STUDENT - PROFILE (FIXED WITH AUTO-FIX)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_STUDENT);

$user = Auth::getCurrentUser();
$message = '';
$error = '';
$student = null;

// Try to get student ID from session first
$studentId = $_SESSION['student_id'] ?? null;

// If no student ID in session, try to find it
if (!$studentId) {
    // Check if user has student_id in database
    $stmt = $pdo->prepare("SELECT student_id FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    
    if ($userData && $userData['student_id']) {
        $studentId = $userData['student_id'];
        $_SESSION['student_id'] = $studentId;
    } else {
        // Try to find student by user_id
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $studentData = $stmt->fetch();
        
        if ($studentData) {
            $studentId = $studentData['student_id'];
            $_SESSION['student_id'] = $studentId;
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET student_id = ? WHERE user_id = ?");
            $stmt->execute([$studentId, $_SESSION['user_id']]);
        }
    }
}

// If still no student ID, try to create a student record
if (!$studentId) {
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    
    if ($userData) {
        // Try to create student record
        try {
            // Get default department
            $dept = $pdo->query("SELECT dept_id FROM departments LIMIT 1")->fetch();
            $deptId = $dept ? $dept['dept_id'] : 1;
            
            // Generate student code
            $studentCode = 'STU' . date('Y') . str_pad($_SESSION['user_id'], 4, '0', STR_PAD_LEFT);
            
            // Insert student record
            $stmt = $pdo->prepare("
                INSERT INTO students (
                    student_code, full_name, email, phone, 
                    department_id, year_of_study, semester, enrollment_status, user_id
                ) VALUES (?, ?, ?, ?, ?, 1, 1, 'active', ?)
            ");
            $stmt->execute([
                $studentCode,
                $userData['full_name'],
                $userData['email'],
                $userData['phone'] ?? '',
                $deptId,
                $_SESSION['user_id']
            ]);
            
            $studentId = $pdo->lastInsertId();
            
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET student_id = ? WHERE user_id = ?");
            $stmt->execute([$studentId, $_SESSION['user_id']]);
            
            $_SESSION['student_id'] = $studentId;
            
            $message = 'Student record created successfully!';
            
        } catch (PDOException $e) {
            die('Could not create student record: ' . $e->getMessage());
        }
    }
}

// Now get student info
if ($studentId) {
    $stmt = $pdo->prepare("
        SELECT s.*, d.dept_name, u.username, u.email, u.phone, u.full_name, u.is_active, u.is_verified
        FROM students s
        JOIN departments d ON s.department_id = d.dept_id
        JOIN users u ON s.user_id = u.user_id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
}

if (!$student) {
    die('Student record not found. Please contact administrator.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Update Profile
        if ($action === 'update_profile') {
            $fullName = Security::sanitizeInput($_POST['full_name']);
            $phone = Security::sanitizeInput($_POST['phone']);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $address = Security::sanitizeInput($_POST['address'] ?? '');
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (empty($fullName)) {
                $error = 'Full name is required.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Update users table
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, phone = ?, email = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$fullName, $phone, $email, $_SESSION['user_id']]);
                    
                    // Update students table
                    $stmt = $pdo->prepare("
                        UPDATE students 
                        SET full_name = ?, phone = ?, email = ?
                        WHERE student_id = ?
                    ");
                    $stmt->execute([$fullName, $phone, $email, $studentId]);
                    
                    $pdo->commit();
                    
                    // Update session
                    $_SESSION['full_name'] = $fullName;
                    
                    $message = 'Profile updated successfully!';
                    Security::logEvent($_SESSION['user_id'], 'UPDATE_PROFILE', 'Updated profile');
                    
                    // Refresh student data
                    $stmt = $pdo->prepare("
                        SELECT s.*, d.dept_name, u.username, u.email, u.phone, u.full_name
                        FROM students s
                        JOIN departments d ON s.department_id = d.dept_id
                        JOIN users u ON s.user_id = u.user_id
                        WHERE s.student_id = ?
                    ");
                    $stmt->execute([$studentId]);
                    $student = $stmt->fetch();
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // Change Password
        elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Get user's current password hash
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch();
            
            if (!Security::verifyPassword($currentPassword, $userData['password_hash'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                $error = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match.';
            } else {
                $validationErrors = Security::validatePassword($newPassword);
                if (!empty($validationErrors)) {
                    $error = implode('<br>', $validationErrors);
                } else {
                    try {
                        $hashedPassword = Security::hashPassword($newPassword);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                        $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                        
                        $message = 'Password changed successfully!';
                        Security::logEvent($_SESSION['user_id'], 'CHANGE_PASSWORD', 'Changed password');
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
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
    <title>Profile - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .profile-card .card-title {
            font-weight: 700;
            color: #2C3E50;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .profile-card .card-title i {
            color: #9b59b6;
            margin-right: 10px;
        }
        .profile-group {
            margin-bottom: 20px;
        }
        .profile-group label {
            font-weight: 600;
            color: #2C3E50;
            font-size: 14px;
        }
        .profile-group .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #9b59b6;
            padding: 3px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 10px;
            border-left: 4px solid #9b59b6;
            margin-bottom: 10px;
        }
        .info-box .label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        .info-box .value {
            font-weight: 600;
            margin-top: 3px;
        }
        .profile-sidebar {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .profile-sidebar .student-id {
            font-size: 14px;
            color: #7f8c8d;
        }
        .profile-sidebar .student-name {
            font-size: 20px;
            font-weight: 700;
        }
        .profile-sidebar .student-program {
            color: #7f8c8d;
        }
        .profile-sidebar .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .profile-sidebar .info-item:last-child {
            border-bottom: none;
        }
        .profile-sidebar .info-item .label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        .profile-sidebar .info-item .value {
            font-weight: 600;
        }
        .alert-fix {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/student_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-user"></i> My Profile</h5>
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
            <!-- Profile Sidebar -->
            <div class="col-lg-4">
                <div class="profile-sidebar text-center">
                    <img src="../../assets/images/avatar.png" alt="Profile" 
                         class="avatar-preview mb-3" onerror="this.src='https://via.placeholder.com/120'">
                    
                    <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                    <div class="student-id"><?php echo htmlspecialchars($student['student_code']); ?></div>
                    <div class="student-program"><?php echo htmlspecialchars($student['program']); ?></div>
                    
                    <hr>
                    
                    <div class="text-start">
                        <div class="info-item">
                            <div class="label">Department</div>
                            <div class="value"><?php echo htmlspecialchars($student['dept_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Year of Study</div>
                            <div class="value">Year <?php echo $student['year_of_study']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Semester</div>
                            <div class="value">Semester <?php echo $student['semester']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Enrollment Status</div>
                            <div class="value">
                                <?php if ($student['enrollment_status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php elseif ($student['enrollment_status'] == 'graduated'): ?>
                                    <span class="badge bg-primary">Graduated</span>
                                <?php elseif ($student['enrollment_status'] == 'suspended'): ?>
                                    <span class="badge bg-danger">Suspended</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Withdrawn</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($student['gpa']): ?>
                        <div class="info-item">
                            <div class="label">GPA</div>
                            <div class="value"><?php echo number_format($student['gpa'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <div class="label">Account Status</div>
                            <div class="value">
                                <i class="fas fa-<?php echo $student['is_active'] ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i>
                                <?php echo $student['is_active'] ? 'Active' : 'Inactive'; ?>
                                <span class="ms-2">
                                    <i class="fas fa-<?php echo $student['is_verified'] ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i>
                                    <?php echo $student['is_verified'] ? 'Verified' : 'Not Verified'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="label">Member Since</div>
                            <div class="value"><?php echo formatDate($student['created_at'], 'F d, Y'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Profile Edit -->
            <div class="col-lg-8">
                <div class="profile-card">
                    <div class="card-title">
                        <i class="fas fa-edit"></i> Edit Profile
                    </div>
                    
                    <form method="POST">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="profile-group">
                            <label>Username</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['username']); ?>" disabled>
                            <div class="help-text">Username cannot be changed</div>
                        </div>
                        
                        <div class="profile-group">
                            <label>Full Name *</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                        </div>
                        
                        <div class="profile-group">
                            <label>Email Address *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>
                        
                        <div class="profile-group">
                            <label>Phone Number</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>"
                                   placeholder="+251 9XX XXX XXX">
                        </div>
                        
                        <div class="profile-group">
                            <label>Address</label>
                            <input type="text" class="form-control" name="address" 
                                   value="<?php echo htmlspecialchars($student['address'] ?? ''); ?>"
                                   placeholder="Your current address">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
                
                <!-- Change Password -->
                <div class="profile-card">
                    <div class="card-title">
                        <i class="fas fa-key"></i> Change Password
                    </div>
                    
                    <form method="POST">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="profile-group">
                            <label>Current Password *</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        
                        <div class="profile-group">
                            <label>New Password *</label>
                            <input type="password" class="form-control" name="new_password" required>
                            <div class="help-text">
                                Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters with uppercase, lowercase, number, and special character
                            </div>
                        </div>
                        
                        <div class="profile-group">
                            <label>Confirm New Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="fas fa-exchange-alt"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>