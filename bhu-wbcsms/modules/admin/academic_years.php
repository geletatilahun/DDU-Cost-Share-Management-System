<?php
// ============================================
// ADMIN - ACADEMIC YEARS MANAGEMENT
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_ADMIN);

$message = '';
$error = '';
$editYear = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // CREATE ACADEMIC YEAR
        if ($action === 'create') {
            $yearCode = Security::sanitizeInput($_POST['year_code']);
            $yearName = Security::sanitizeInput($_POST['year_name']);
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $isCurrent = isset($_POST['is_current']) ? 1 : 0;
            
            try {
                // If setting as current, unset others
                if ($isCurrent) {
                    $pdo->query("UPDATE academic_years SET is_current = 0");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO academic_years (year_code, year_name, start_date, end_date, is_current)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$yearCode, $yearName, $startDate, $endDate, $isCurrent]);
                
                $message = 'Academic year created successfully!';
                Security::logEvent($_SESSION['user_id'], 'CREATE_ACADEMIC_YEAR', "Created: {$yearName}");
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // UPDATE ACADEMIC YEAR
        elseif ($action === 'update') {
            $yearId = (int)$_POST['year_id'];
            $yearCode = Security::sanitizeInput($_POST['year_code']);
            $yearName = Security::sanitizeInput($_POST['year_name']);
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $isCurrent = isset($_POST['is_current']) ? 1 : 0;
            $status = $_POST['status'];
            
            try {
                // If setting as current, unset others
                if ($isCurrent) {
                    $pdo->query("UPDATE academic_years SET is_current = 0");
                }
                
                $stmt = $pdo->prepare("
                    UPDATE academic_years 
                    SET year_code = ?, year_name = ?, start_date = ?, end_date = ?, 
                        is_current = ?, status = ?
                    WHERE ac_year_id = ?
                ");
                $stmt->execute([$yearCode, $yearName, $startDate, $endDate, $isCurrent, $status, $yearId]);
                
                $message = 'Academic year updated successfully!';
                Security::logEvent($_SESSION['user_id'], 'UPDATE_ACADEMIC_YEAR', "Updated: {$yearName}");
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // DELETE ACADEMIC YEAR
        elseif ($action === 'delete') {
            $yearId = (int)$_POST['year_id'];
            
            // Check if year has applications
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cost_share_applications WHERE ac_year_id = ?");
            $stmt->execute([$yearId]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Cannot delete academic year with existing applications.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM academic_years WHERE ac_year_id = ?");
                $stmt->execute([$yearId]);
                $message = 'Academic year deleted successfully!';
                Security::logEvent($_SESSION['user_id'], 'DELETE_ACADEMIC_YEAR', "Deleted year ID: {$yearId}");
            }
        }
    }
}

// Get edit data
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM academic_years WHERE ac_year_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editYear = $stmt->fetch();
}

// Get all academic years
$years = $pdo->query("
    SELECT y.*,
           (SELECT COUNT(*) FROM cost_share_applications WHERE ac_year_id = y.ac_year_id) as app_count
    FROM academic_years y
    ORDER BY y.start_date DESC
")->fetchAll();

// Get current year
$currentYear = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Years - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        .current-year-badge {
            background: #2ecc71;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        .year-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .year-card.current {
            border-left-color: #2ecc71;
        }
    </style>
</head>
<body>
    <?php include '../../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-calendar-alt"></i> Academic Years</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addYearModal">
                <i class="fas fa-plus"></i> Add Academic Year
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
        
        <!-- Current Year Info -->
        <?php if ($currentYear): ?>
        <div class="year-card current">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5><span class="current-year-badge"><i class="fas fa-check-circle"></i> CURRENT</span></h5>
                    <h4><?php echo htmlspecialchars($currentYear['year_name']); ?></h4>
                    <p class="text-muted">
                        <i class="fas fa-calendar-day"></i> 
                        <?php echo date('M d, Y', strtotime($currentYear['start_date'])); ?> - 
                        <?php echo date('M d, Y', strtotime($currentYear['end_date'])); ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <span class="badge bg-primary">Active</span>
                    <span class="badge bg-info"><?php echo $currentYear['year_code']; ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Academic Years Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="yearTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Year Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Applications</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($years as $year): ?>
                        <tr class="<?php echo $year['is_current'] ? 'table-success' : ''; ?>">
                            <td><strong><?php echo htmlspecialchars($year['year_code']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($year['year_name']); ?>
                                <?php if ($year['is_current']): ?>
                                    <span class="badge bg-success ms-2">Current</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($year['start_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($year['end_date'])); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $year['app_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($year['status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Closed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?php echo $year['ac_year_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($year['app_count'] == 0): ?>
                                    <button class="btn btn-outline-danger" onclick="deleteYear(<?php echo $year['ac_year_id']; ?>)">
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
    
    <!-- Add Year Modal -->
    <div class="modal fade" id="addYearModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Academic Year</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Year Code *</label>
                            <input type="text" class="form-control" name="year_code" 
                                   placeholder="e.g., 2024-25" required>
                            <small class="text-muted">Short code (e.g., 2024-25)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Year Name *</label>
                            <input type="text" class="form-control" name="year_name" 
                                   placeholder="e.g., Academic Year 2024/2025" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_current" id="isCurrent">
                                <label class="form-check-label" for="isCurrent">
                                    Set as current academic year
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Academic Year</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Year Modal -->
    <?php if ($editYear): ?>
    <div class="modal fade show" id="editYearModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Academic Year</h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="year_id" value="<?php echo $editYear['ac_year_id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Year Code *</label>
                            <input type="text" class="form-control" name="year_code" 
                                   value="<?php echo htmlspecialchars($editYear['year_code']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Year Name *</label>
                            <input type="text" class="form-control" name="year_name" 
                                   value="<?php echo htmlspecialchars($editYear['year_name']); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" 
                                           value="<?php echo $editYear['start_date']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" name="end_date" 
                                           value="<?php echo $editYear['end_date']; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_current" id="editIsCurrent" 
                                       <?php echo $editYear['is_current'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="editIsCurrent">
                                    Set as current academic year
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo $editYear['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="closed" <?php echo $editYear['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Academic Year</button>
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
            $('#yearTable').DataTable({
                order: [[2, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search years:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ academic years"
                }
            });
        });
        
        function deleteYear(yearId) {
            if (confirm('Are you sure you want to delete this academic year? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="year_id" value="${yearId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>