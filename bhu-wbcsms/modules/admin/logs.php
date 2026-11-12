<?php
// ============================================
// ADMIN - AUDIT LOGS
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole(ROLE_ADMIN);

// Filter logs
$filter = $_GET['filter'] ?? 'all';
$dateFilter = $_GET['date'] ?? '';

$query = "SELECT l.*, u.username, u.full_name 
          FROM audit_logs l
          LEFT JOIN users u ON l.user_id = u.user_id
          WHERE 1=1";

if ($filter != 'all') {
    $query .= " AND l.action_type = ?";
}
if ($dateFilter) {
    $query .= " AND DATE(l.created_at) = ?";
}
$query .= " ORDER BY l.created_at DESC LIMIT 1000";

$stmt = $pdo->prepare($query);

$params = [];
if ($filter != 'all') $params[] = $filter;
if ($dateFilter) $params[] = $dateFilter;
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get distinct action types for filter
$actions = $pdo->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include '../../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-list"></i> Audit Logs</h5>
        </div>
        
        <div class="table-container">
            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <form method="GET" class="d-flex gap-2">
                        <select name="filter" class="form-select">
                            <option value="all">All Actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo $action['action_type']; ?>" 
                                    <?php echo $filter == $action['action_type'] ? 'selected' : ''; ?>>
                                    <?php echo $action['action_type']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date" class="form-control" value="<?php echo $dateFilter; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="logs.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </form>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="logsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['log_id']; ?></td>
                            <td>
                                <?php if ($log['username']): ?>
                                    <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($log['full_name']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($log['action_type']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($log['action_description']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $log['status'] == 'success' ? 'success' : 'danger'; ?>">
                                    <?php echo $log['status']; ?>
                                </span>
                            </td>
                            <td><?php echo formatDateTime($log['created_at']); ?></td>
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
            $('#logsTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 50,
                language: {
                    search: "Search logs:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ logs",
                }
            });
        });
    </script>
</body>
</html>