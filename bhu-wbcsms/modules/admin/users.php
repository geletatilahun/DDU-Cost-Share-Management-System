<?php
// ============================================
// ADMIN - USER MANAGEMENT (COMPLETE)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_ADMIN);

$message = '';
$error = '';
$editUser = null;

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // CREATE USER
        if ($action === 'create') {
            $username = Security::sanitizeInput($_POST['username']);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $fullName = Security::sanitizeInput($_POST['full_name']);
            $roleId = (int)$_POST['role_id'];
            $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
            $phone = Security::sanitizeInput($_POST['phone'] ?? '');
            
            // Validate
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (strlen($username) < 3) {
                $error = 'Username must be at least 3 characters.';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Username or email already exists.';
                    } else {
                        $password = $_POST['password'] ?? Security::generatePassword();
                        $hashedPassword = Security::hashPassword($password);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, email, password_hash, full_name, role_id, dept_id, phone, is_verified)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$username, $email, $hashedPassword, $fullName, $roleId, $deptId, $phone]);
                        
                        $message = 'User created successfully! Password: ' . $password;
                        
                        // Send welcome email
                        sendEmail($email, 'Welcome to WBCSMS', "
                            <h3>Welcome to WBCSMS</h3>
                            <p>Your account has been created successfully.</p>
                            <p><strong>Username:</strong> {$username}</p>
                            <p><strong>Password:</strong> {$password}</p>
                            <p><a href='" . SITE_URL . "login.php'>Click here to login</a></p>
                            <p>Please change your password after first login.</p>
                        ");
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        // UPDATE USER
        elseif ($action === 'update') {
            $userId = (int)$_POST['user_id'];
            $fullName = Security::sanitizeInput($_POST['full_name']);
            $roleId = (int)$_POST['role_id'];
            $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
            $phone = Security::sanitizeInput($_POST['phone'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, role_id = ?, dept_id = ?, phone = ?, is_active = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$fullName, $roleId, $deptId, $phone, $isActive, $userId]);
                
                // Update password if provided
                if (!empty($_POST['new_password'])) {
                    $newPassword = $_POST['new_password'];
                    $hashedPassword = Security::hashPassword($newPassword);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    $message = 'User updated and password changed!';
                } else {
                    $message = 'User updated successfully!';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
        
        // DELETE USER
        elseif ($action === 'delete') {
            $userId = (int)$_POST['user_id'];
            if ($userId == $_SESSION['user_id']) {
                $error = 'You cannot delete your own account.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $message = 'User deleted successfully.';
                Security::logEvent($_SESSION['user_id'], 'DELETE_USER', "Deleted user ID: {$userId}");
            }
        }
        
        // TOGGLE STATUS
        elseif ($action === 'toggle_status') {
            $userId = (int)$_POST['user_id'];
            $status = $_POST['status'] === 'active' ? 1 : 0;
            
            if ($userId == $_SESSION['user_id'] && $status == 0) {
                $error = 'You cannot deactivate your own account.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
                $stmt->execute([$status, $userId]);
                $message = 'User status updated successfully.';
                Security::logEvent($_SESSION['user_id'], 'TOGGLE_USER_STATUS', 
                                  "User ID: {$userId} set to " . ($status ? 'active' : 'inactive'));
            }
        }
    }
}

// Get edit user data
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_GET['edit']]);
    $editUser = $stmt->fetch();
}

// Get all users
$users = $pdo->query("
    SELECT u.*, r.role_name, d.dept_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    ORDER BY u.user_id DESC
")->fetchAll();

// Get roles and departments for dropdowns
$roles = $pdo->query("SELECT * FROM roles ORDER BY role_id")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments ORDER BY dept_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include '../../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-users"></i> User Management</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i> Add User
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
        
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $user['role_id'] == 1 ? 'danger' : 
                                        ($user['role_id'] == 2 ? 'primary' : 
                                        ($user['role_id'] == 3 ? 'warning' : 
                                        ($user['role_id'] == 4 ? 'info' : 'success'))); 
                                ?>">
                                    <?php echo ucfirst($user['role_name']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['dept_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted">
                                    <?php echo $user['is_verified'] ? '✓ Verified' : '✗ Unverified'; ?>
                                </small>
                            </td>
                            <td>
                                <?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?php echo $user['user_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-outline-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" 
                                            onclick="toggleStatus(<?php echo $user['user_id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)">
                                        <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
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
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" required>
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
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" placeholder="+251 9XX XXX XXX">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role *</label>
                                    <select class="form-select" name="role_id" required>
                                        <option value="">Select Role</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['role_id']; ?>">
                                                <?php echo ucfirst($role['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="dept_id">
                                        <option value="">None</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['dept_id']; ?>">
                                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="auto_password" checked>
                                <label class="form-check-label" for="auto_password">
                                    Auto-generate password (will be emailed)
                                </label>
                            </div>
                            <input type="text" class="form-control mt-2 d-none" id="manual_password" 
                                   name="password" placeholder="Enter custom password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <?php if ($editUser): ?>
    <div class="modal fade show" id="editUserModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit User</h5>
                    <a href="?" class="btn-close"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" value="<?php echo $editUser['user_id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($editUser['username']); ?>" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?php echo htmlspecialchars($editUser['full_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($editUser['email']); ?>" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" 
                                           value="<?php echo htmlspecialchars($editUser['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role *</label>
                                    <select class="form-select" name="role_id" required>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['role_id']; ?>" 
                                                <?php echo $role['role_id'] == $editUser['role_id'] ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($role['role_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="dept_id">
                                        <option value="">None</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['dept_id']; ?>"
                                                <?php echo $dept['dept_id'] == $editUser['dept_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_active" 
                                               <?php echo $editUser['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Active</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="text" class="form-control" name="new_password" 
                                           placeholder="Leave empty to keep current password">
                                    <small class="text-muted">Leave empty to keep current password</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="?" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update User</button>
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
            $('#usersTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ users",
                }
            });
        });
        
        document.getElementById('auto_password').addEventListener('change', function() {
            document.getElementById('manual_password').classList.toggle('d-none');
        });
        
        function toggleStatus(userId, status) {
            if (confirm('Are you sure you want to ' + (status ? 'activate' : 'deactivate') + ' this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="status" value="${status ? 'active' : 'inactive'}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>