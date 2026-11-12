<?php
// ============================================
// ADMIN - DEPARTMENT MANAGEMENT
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_ADMIN);

$message = '';
$error = '';
$editDept = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // CREATE DEPARTMENT
        if ($action === 'create') {
            $deptCode = strtoupper(Security::sanitizeInput($_POST['dept_code']));
            $deptName = Security::sanitizeInput($_POST['dept_name']);
            $faculty = Security::sanitizeInput($_POST['faculty']);
            $budget = (float)$_POST['budget_allocation'];
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO departments (dept_code, dept_name, faculty, budget_allocation)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$deptCode, $deptName, $faculty, $budget]);
                
                $message = 'Department created successfully!';
                Security::logEvent($_SESSION['user_id'], 'CREATE_DEPARTMENT', "Created department: {$deptName}");
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // UPDATE DEPARTMENT
        elseif ($action === 'update') {
            $deptId = (int)$_POST['dept_id'];
            $deptCode = strtoupper(Security::sanitizeInput($_POST['dept_code']));
            $deptName = Security::sanitizeInput($_POST['dept_name']);
            $faculty = Security::sanitizeInput($_POST['faculty']);
            $budget = (float)$_POST['budget_allocation'];
            $status = $_POST['status'];
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE departments 
                    SET dept_code = ?, dept_name = ?, faculty = ?, budget_allocation = ?, status = ?
                    WHERE dept_id = ?
                ");
                $stmt->execute([$deptCode, $deptName, $faculty, $budget, $status, $deptId]);
                
                $message = 'Department updated successfully!';
                Security::logEvent($_SESSION['user_id'], 'UPDATE_DEPARTMENT', "Updated department: {$deptName}");
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // DELETE DEPARTMENT
        elseif ($action === 'delete') {
            $deptId = (int)$_POST['dept_id'];
            
            // Check if department has users
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE dept_id = ?");
            $stmt->execute([$deptId]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Cannot delete department with assigned users.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM departments WHERE dept_id = ?");
                $stmt->execute([$deptId]);
                $message = 'Department deleted successfully!';
                Security::logEvent($_SESSION['user_id'], 'DELETE_DEPARTMENT', "Deleted department ID: {$deptId}");
            }
        }
    }
}

// Get edit data
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE dept_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editDept = $stmt->fetch();
}

// Get all departments
$departments = $pdo->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM users WHERE dept_id = d.dept_id) as user_count,
           (SELECT COUNT(*) FROM students WHERE department_id = d.dept_id) as student_count
    FROM departments d
    ORDER BY d.dept_name
")->fetchAll();

// Get stats
$totalDepts = count($departments);
$totalBudget = array_sum(array_column($departments, 'budget_allocation'));
$activeDepts = array_filter($departments, function($d) { return $d['status'] == 'active'; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
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
            opacity: 0.3;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-building"></i> Department Management</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                <i class="fas fa-plus"></i> Add Department
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
                    <div class="icon"><i class="fas fa-building"></i></div>
                    <div class="number text-primary"><?php echo $totalDepts; ?></div>
                    <div class="label">Total Departments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="number text-success"><?php echo count($activeDepts); ?></div>
                    <div class="label">Active Departments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="number text-warning"><?php echo formatCurrency($totalBudget); ?></div>
                    <div class="label">Total Budget</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(DISTINCT dept_id) FROM users WHERE dept_id IS NOT NULL");
                    $deptWithUsers = $stmt->fetchColumn();
                    ?>
                    <div class="number text-info"><?php echo $deptWithUsers; ?></div>
                    <div class="label">Departments with Staff</div>
                </div>
            </div>
        </div>
        
        <!-- Departments Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="deptTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Faculty</th>
                            <th>Budget</th>
                            <th>Users</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($dept['dept_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($dept['dept_name']); ?></td>
                            <td><?php echo htmlspecialchars($dept['faculty'] ?? 'N/A'); ?></td>
                            <td><?php echo formatCurrency($dept['budget_allocation']); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $dept['user_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $dept['student_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($dept['status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?php echo $dept['dept_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($dept['user_count'] == 0 && $dept['student_count'] == 0): ?>
                                    <button class="btn btn-outline-danger" onclick="deleteDept(<?php echo $dept['dept_id']; ?>)">
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
    
    <!-- Add Department Modal -->
    <div class="modal fade" id="addDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Department Code *</label>
                            <input type="text" class="form-control" name="dept_code" 
                                   placeholder="e.g., CSE" required maxlength="10">
                            <small class="text-muted">Short unique code (e.g., CSE, EEE)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" class="form-control" name="dept_name" 
                                   placeholder="e.g., Computer Science and Engineering" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Faculty</label>
                            <input type="text" class="form-control" name="faculty" 
                                   placeholder="e.g., Faculty of Technology">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Budget Allocation</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" class="form-control" name="budget_allocation" 
                                       step="1000" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Department Modal -->
    <?php if ($editDept): ?>
    <div class="modal fade show" id="editDeptModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Department</h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="dept_id" value="<?php echo $editDept['dept_id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Department Code *</label>
                            <input type="text" class="form-control" name="dept_code" 
                                   value="<?php echo htmlspecialchars($editDept['dept_code']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" class="form-control" name="dept_name" 
                                   value="<?php echo htmlspecialchars($editDept['dept_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Faculty</label>
                            <input type="text" class="form-control" name="faculty" 
                                   value="<?php echo htmlspecialchars($editDept['faculty'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Budget Allocation</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" class="form-control" name="budget_allocation" 
                                       step="1000" min="0" value="<?php echo $editDept['budget_allocation']; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo $editDept['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $editDept['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Department</button>
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
            $('#deptTable').DataTable({
                order: [[1, 'asc']],
                pageLength: 25,
                language: {
                    search: "Search departments:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ departments"
                }
            });
        });
        
        function deleteDept(deptId) {
            if (confirm('Are you sure you want to delete this department? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="dept_id" value="${deptId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>