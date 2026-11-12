<?php
// ============================================
// OFFICER - STUDENT MANAGEMENT
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_OFFICER);

$message = '';
$error = '';
$editStudent = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // CREATE STUDENT
        if ($action === 'create') {
            $studentCode = strtoupper(Security::sanitizeInput($_POST['student_code']));
            $fullName = Security::sanitizeInput($_POST['full_name']);
            $gender = $_POST['gender'];
            $dob = $_POST['date_of_birth'];
            $phone = Security::sanitizeInput($_POST['phone']);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $program = Security::sanitizeInput($_POST['program']);
            $departmentId = (int)$_POST['department_id'];
            $yearOfStudy = (int)$_POST['year_of_study'];
            $semester = (int)$_POST['semester'];
            $academicYear = Security::sanitizeInput($_POST['academic_year']);
            $gpa = (float)$_POST['gpa'];
            $enrollmentStatus = $_POST['enrollment_status'];
            
            // Validate
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } else {
                try {
                    // Check if student code exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_code = ?");
                    $stmt->execute([$studentCode]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Student code already exists.';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO students (student_code, full_name, gender, date_of_birth, phone, email,
                                                 program, department_id, year_of_study, semester, academic_year, 
                                                 gpa, enrollment_status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $studentCode, $fullName, $gender, $dob, $phone, $email,
                            $program, $departmentId, $yearOfStudy, $semester, $academicYear,
                            $gpa, $enrollmentStatus
                        ]);
                        
                        $message = 'Student added successfully!';
                        Security::logEvent($_SESSION['user_id'], 'CREATE_STUDENT', "Added student: {$fullName}");
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // UPDATE STUDENT
        elseif ($action === 'update') {
            $studentId = (int)$_POST['student_id'];
            $fullName = Security::sanitizeInput($_POST['full_name']);
            $gender = $_POST['gender'];
            $dob = $_POST['date_of_birth'];
            $phone = Security::sanitizeInput($_POST['phone']);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $program = Security::sanitizeInput($_POST['program']);
            $departmentId = (int)$_POST['department_id'];
            $yearOfStudy = (int)$_POST['year_of_study'];
            $semester = (int)$_POST['semester'];
            $academicYear = Security::sanitizeInput($_POST['academic_year']);
            $gpa = (float)$_POST['gpa'];
            $enrollmentStatus = $_POST['enrollment_status'];
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET full_name = ?, gender = ?, date_of_birth = ?, phone = ?, email = ?,
                        program = ?, department_id = ?, year_of_study = ?, semester = ?,
                        academic_year = ?, gpa = ?, enrollment_status = ?
                    WHERE student_id = ?
                ");
                $stmt->execute([
                    $fullName, $gender, $dob, $phone, $email,
                    $program, $departmentId, $yearOfStudy, $semester,
                    $academicYear, $gpa, $enrollmentStatus, $studentId
                ]);
                
                $message = 'Student updated successfully!';
                Security::logEvent($_SESSION['user_id'], 'UPDATE_STUDENT', "Updated student ID: {$studentId}");
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // DELETE STUDENT
        elseif ($action === 'delete') {
            $studentId = (int)$_POST['student_id'];
            
            // Check if student has applications
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cost_share_applications WHERE student_id = ?");
            $stmt->execute([$studentId]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Cannot delete student with existing applications.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
                $stmt->execute([$studentId]);
                $message = 'Student deleted successfully!';
                Security::logEvent($_SESSION['user_id'], 'DELETE_STUDENT', "Deleted student ID: {$studentId}");
            }
        }
        
        // UPLOAD STUDENTS (CSV)
        elseif ($action === 'upload') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, 'r');
                $header = fgetcsv($handle);
                
                $successCount = 0;
                $errorCount = 0;
                $errors = [];
                
                while (($row = fgetcsv($handle)) !== FALSE) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO students (student_code, full_name, program, department_id, 
                                                 year_of_study, semester, academic_year, enrollment_status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                        ");
                        $stmt->execute([
                            $row[0], $row[1], $row[2],
                            (int)$row[3], (int)$row[4], (int)$row[5], $row[6]
                        ]);
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        $errors[] = "Row " . ($successCount + $errorCount) . ": " . $e->getMessage();
                    }
                }
                fclose($handle);
                
                $message = "Upload complete! {$successCount} students added. {$errorCount} errors.";
                if (!empty($errors)) {
                    $error = "Errors: " . implode(', ', array_slice($errors, 0, 5));
                }
                Security::logEvent($_SESSION['user_id'], 'UPLOAD_STUDENTS', "Uploaded {$successCount} students");
            } else {
                $error = 'Please select a valid CSV file.';
            }
        }
    }
}

// Get edit data
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editStudent = $stmt->fetch();
}

// Get all students
$students = $pdo->query("
    SELECT s.*, d.dept_name, d.dept_code
    FROM students s
    JOIN departments d ON s.department_id = d.dept_id
    ORDER BY s.created_at DESC
")->fetchAll();

// Get departments for dropdown
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY dept_name")->fetchAll();

// Get stats
$totalStudents = count($students);
$activeStudents = array_filter($students, function($s) { return $s['enrollment_status'] == 'active'; });
$graduatedStudents = array_filter($students, function($s) { return $s['enrollment_status'] == 'graduated'; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - <?php echo SITE_NAME; ?></title>
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
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .upload-area {
            border: 2px dashed #e9ecef;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        .upload-area i {
            font-size: 40px;
            color: #bdc3c7;
        }
    </style>
</head>
<body>
    <?php include '../../includes/officer_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-user-graduate"></i> Student Management</h5>
            <div>
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload"></i> Upload CSV
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="fas fa-plus"></i> Add Student
                </button>
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
                    <div class="number text-primary"><?php echo $totalStudents; ?></div>
                    <div class="label">Total Students</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-success"><?php echo count($activeStudents); ?></div>
                    <div class="label">Active Students</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="number text-warning"><?php echo count($graduatedStudents); ?></div>
                    <div class="label">Graduated</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(DISTINCT program) FROM students");
                    $programs = $stmt->fetchColumn();
                    ?>
                    <div class="number text-info"><?php echo $programs; ?></div>
                    <div class="label">Programs</div>
                </div>
            </div>
        </div>
        
        <!-- Students Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="studentTable">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Program</th>
                            <th>Department</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['program']); ?></td>
                            <td><?php echo htmlspecialchars($student['dept_code']); ?></td>
                            <td><?php echo $student['year_of_study']; ?></td>
                            <td><?php echo $student['semester']; ?></td>
                            <td>
                                <?php if ($student['enrollment_status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php elseif ($student['enrollment_status'] == 'graduated'): ?>
                                    <span class="badge bg-primary">Graduated</span>
                                <?php elseif ($student['enrollment_status'] == 'suspended'): ?>
                                    <span class="badge bg-danger">Suspended</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Withdrawn</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?php echo $student['student_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cost_share_applications WHERE student_id = ?");
                                    $stmt->execute([$student['student_id']]);
                                    $hasApps = $stmt->fetchColumn() > 0;
                                    ?>
                                    <?php if (!$hasApps): ?>
                                    <button class="btn btn-outline-danger" onclick="deleteStudent(<?php echo $student['student_id']; ?>)">
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
    
    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Student ID *</label>
                                    <input type="text" class="form-control" name="student_code" 
                                           placeholder="e.g., STU2024001" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           placeholder="student@email.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" 
                                           placeholder="+251 9XX XXX XXX">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Program *</label>
                                    <input type="text" class="form-control" name="program" 
                                           placeholder="e.g., Computer Science" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department *</label>
                                    <select class="form-select" name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['dept_id']; ?>">
                                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Year of Study</label>
                                    <input type="number" class="form-control" name="year_of_study" 
                                           min="1" max="5" value="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Semester</label>
                                    <input type="number" class="form-control" name="semester" 
                                           min="1" max="2" value="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Academic Year</label>
                                    <input type="text" class="form-control" name="academic_year" 
                                           placeholder="e.g., 2024-25">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">GPA</label>
                                    <input type="number" class="form-control" name="gpa" 
                                           step="0.01" min="0" max="4" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Enrollment Status</label>
                                    <select class="form-select" name="enrollment_status">
                                        <option value="active">Active</option>
                                        <option value="graduated">Graduated</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="withdrawn">Withdrawn</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Upload CSV Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload"></i> Upload Students (CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="upload-area" onclick="document.getElementById('csvFile').click()">
                            <i class="fas fa-file-csv"></i>
                            <p class="mt-2">Click to select CSV file</p>
                            <small class="text-muted">Supported format: .csv</small>
                            <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display: none;" required>
                        </div>
                        
                        <div class="mt-3">
                            <h6>CSV Format Instructions:</h6>
                            <ul class="small text-muted">
                                <li>Columns: student_code, full_name, program, department_id, year, semester, academic_year</li>
                                <li>Example: STU2024001, John Doe, Computer Science, 1, 2, 1, 2024-25</li>
                                <li>Department IDs: Check departments table</li>
                            </ul>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-download"></i> Download Template
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Student Modal -->
    <?php if ($editStudent): ?>
    <div class="modal fade show" id="editStudentModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Student</h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="student_id" value="<?php echo $editStudent['student_id']; ?>">
                        
                        <!-- Student Code (readonly) -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Student ID</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars($editStudent['student_code']); ?>" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?php echo htmlspecialchars($editStudent['full_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="Male" <?php echo $editStudent['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $editStudent['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo $editStudent['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth" 
                                           value="<?php echo $editStudent['date_of_birth']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($editStudent['email']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" 
                                           value="<?php echo htmlspecialchars($editStudent['phone']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Program *</label>
                                    <input type="text" class="form-control" name="program" 
                                           value="<?php echo htmlspecialchars($editStudent['program']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department *</label>
                                    <select class="form-select" name="department_id" required>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['dept_id']; ?>"
                                                <?php echo $dept['dept_id'] == $editStudent['department_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Year of Study</label>
                                    <input type="number" class="form-control" name="year_of_study" 
                                           value="<?php echo $editStudent['year_of_study']; ?>" min="1" max="5">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Semester</label>
                                    <input type="number" class="form-control" name="semester" 
                                           value="<?php echo $editStudent['semester']; ?>" min="1" max="2">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Academic Year</label>
                                    <input type="text" class="form-control" name="academic_year" 
                                           value="<?php echo htmlspecialchars($editStudent['academic_year']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">GPA</label>
                                    <input type="number" class="form-control" name="gpa" 
                                           value="<?php echo $editStudent['gpa']; ?>" step="0.01" min="0" max="4">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Enrollment Status</label>
                                    <select class="form-select" name="enrollment_status">
                                        <option value="active" <?php echo $editStudent['enrollment_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="graduated" <?php echo $editStudent['enrollment_status'] == 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                                        <option value="suspended" <?php echo $editStudent['enrollment_status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        <option value="withdrawn" <?php echo $editStudent['enrollment_status'] == 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#studentTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search students:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ students"
                }
            });
        });
        
        function deleteStudent(studentId) {
            if (confirm('Are you sure you want to delete this student? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="student_id" value="${studentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Show file name when selected
        document.getElementById('csvFile').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file selected';
            document.querySelector('.upload-area p').textContent = 'Selected: ' + fileName;
        });
    </script>
</body>
</html>