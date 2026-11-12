<?php
// ============================================
// HOD - DEPARTMENT STUDENTS
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_HOD);

$deptId = $_SESSION['dept_id'] ?? 0;

if (!$deptId) {
    die('Department not assigned. Please contact administrator.');
}

$message = '';
$error = '';

// Get department info
$stmt = $pdo->prepare("SELECT * FROM departments WHERE dept_id = ?");
$stmt->execute([$deptId]);
$department = $stmt->fetch();

// Get all students in department
$stmt = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM cost_share_applications WHERE student_id = s.student_id) as app_count,
           (SELECT COUNT(*) FROM cost_share_applications WHERE student_id = s.student_id AND status IN ('approved', 'disbursed')) as approved_count
    FROM students s
    WHERE s.department_id = ?
    ORDER BY s.full_name
");
$stmt->execute([$deptId]);
$students = $stmt->fetchAll();

// Get statistics
$totalStudents = count($students);
$activeStudents = array_filter($students, function($s) { return $s['enrollment_status'] == 'active'; });
$graduatedStudents = array_filter($students, function($s) { return $s['enrollment_status'] == 'graduated'; });
$studentsWithApps = array_filter($students, function($s) { return $s['app_count'] > 0; });

// Get program distribution
$programs = [];
foreach ($students as $student) {
    if (!isset($programs[$student['program']])) {
        $programs[$student['program']] = 0;
    }
    $programs[$student['program']]++;
}
arsort($programs);

// Get year distribution
$years = [];
foreach ($students as $student) {
    $year = 'Year ' . $student['year_of_study'];
    if (!isset($years[$year])) {
        $years[$year] = 0;
    }
    $years[$year]++;
}
ksort($years);

// Get gender distribution
$gender = [];
foreach ($students as $student) {
    if (!isset($gender[$student['gender']])) {
        $gender[$student['gender']] = 0;
    }
    $gender[$student['gender']]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Students - <?php echo SITE_NAME; ?></title>
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
        .distribution-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .distribution-card .item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .distribution-card .item:last-child {
            border-bottom: none;
        }
        .distribution-card .count {
            font-weight: 600;
        }
        .progress-bar-custom {
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
        }
        .dept-header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .dept-header h4 {
            margin: 0;
            font-weight: 700;
        }
        .dept-header small {
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <?php include '../../includes/hod_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-user-graduate"></i> Department Students</h5>
            <div>
                <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="exportStudents()">
                    <i class="fas fa-file-export"></i> Export
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
        
        <!-- Department Header -->
        <div class="dept-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4><i class="fas fa-building text-primary"></i> <?php echo htmlspecialchars($department['dept_name']); ?></h4>
                    <small>Code: <?php echo htmlspecialchars($department['dept_code']); ?> | Faculty: <?php echo htmlspecialchars($department['faculty'] ?? 'N/A'); ?></small>
                </div>
                <div class="col-md-6 text-end">
                    <span class="badge bg-primary me-2">Total: <?php echo $totalStudents; ?></span>
                    <span class="badge bg-success me-2">Active: <?php echo count($activeStudents); ?></span>
                    <span class="badge bg-info">With Applications: <?php echo count($studentsWithApps); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <div class="number text-primary"><?php echo $totalStudents; ?></div>
                    <div class="label">Total Students</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="number text-success"><?php echo count($activeStudents); ?></div>
                    <div class="label">Active Students</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="number text-warning"><?php echo count($graduatedStudents); ?></div>
                    <div class="label">Graduated</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-file-alt"></i></div>
                    <div class="number text-info"><?php echo count($studentsWithApps); ?></div>
                    <div class="label">With Applications</div>
                </div>
            </div>
        </div>
        
        <!-- Distributions -->
        <div class="row g-4 mb-4">
            <!-- Program Distribution -->
            <div class="col-md-4">
                <div class="distribution-card">
                    <h6><i class="fas fa-graduation-cap me-2"></i> Program Distribution</h6>
                    <?php foreach ($programs as $program => $count): ?>
                        <div class="item">
                            <span><?php echo htmlspecialchars($program); ?></span>
                            <span class="count"><?php echo $count; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Year Distribution -->
            <div class="col-md-4">
                <div class="distribution-card">
                    <h6><i class="fas fa-layer-group me-2"></i> Year Distribution</h6>
                    <?php foreach ($years as $year => $count): ?>
                        <div class="item">
                            <span><?php echo $year; ?></span>
                            <span class="count"><?php echo $count; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Gender Distribution -->
            <div class="col-md-4">
                <div class="distribution-card">
                    <h6><i class="fas fa-venus-mars me-2"></i> Gender Distribution</h6>
                    <?php foreach ($gender as $g => $count): ?>
                        <div class="item">
                            <span><?php echo $g; ?></span>
                            <span class="count"><?php echo $count; ?></span>
                        </div>
                    <?php endforeach; ?>
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
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Applications</th>
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
                            <td><?php echo $student['year_of_study']; ?></td>
                            <td><?php echo $student['semester']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $student['app_count'] > 0 ? 'primary' : 'secondary'; ?>">
                                    <?php echo $student['app_count']; ?>
                                </span>
                                <?php if ($student['approved_count'] > 0): ?>
                                    <span class="badge bg-success"><?php echo $student['approved_count']; ?> Approved</span>
                                <?php endif; ?>
                            </td>
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
                                <a href="#" class="btn btn-sm btn-outline-primary" onclick="viewStudent(<?php echo $student['student_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#studentTable').DataTable({
                order: [[1, 'asc']],
                pageLength: 25,
                language: {
                    search: "Search students:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ students"
                }
            });
        });
        
        function viewStudent(studentId) {
            alert('Student details will be shown here. Student ID: ' + studentId);
        }
        
        function exportStudents() {
            alert('Export functionality will be implemented.');
        }
    </script>
</body>
</html>