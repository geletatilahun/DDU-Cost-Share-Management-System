<?php
// ============================================
// ADMIN - DATABASE BACKUP (FIXED)
// ============================================

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';

Auth::requireRole(ROLE_ADMIN);

$message = '';
$error = '';

// Create backup directory if not exists
$backupDir = __DIR__ . '/../../backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Get list of backups
$backups = glob($backupDir . '*.sql');
rsort($backups);

// ============================================
// METHOD 1: PHP-Based Backup (No mysqldump)
// ============================================
function backupDatabasePHP($pdo, $backupDir) {
    try {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $output = "-- ============================================\n";
        $output .= "-- WBCSMS Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Database: " . DB_NAME . "\n";
        $output .= "-- ============================================\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        foreach ($tables as $table) {
            // Get create table statement
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $create = $stmt->fetch();
            $output .= "-- Table structure for table `$table`\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $create['Create Table'] . ";\n\n";
            
            // Get table data
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $output .= "-- Dumping data for table `$table`\n";
                $output .= "INSERT INTO `$table` (`" . implode('`, `', array_keys($rows[0])) . "`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $escaped = array_map(function($value) use ($pdo) {
                        if ($value === null) return 'NULL';
                        return $pdo->quote($value);
                    }, $row);
                    $values[] = "(" . implode(', ', $escaped) . ")";
                }
                $output .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        // Write to file
        file_put_contents($filepath, $output);
        
        // Compress file
        $zipPath = $filepath . '.gz';
        $fp = gzopen($zipPath, 'w9');
        gzwrite($fp, $output);
        gzclose($fp);
        
        // Delete uncompressed file
        unlink($filepath);
        
        return ['success' => true, 'filename' => $filename . '.gz', 'path' => $zipPath, 'method' => 'PHP'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================
// METHOD 2: mysqldump Backup
// ============================================
function backupDatabaseMysqldump($backupDir) {
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . $filename;
    
    // Try to find mysqldump
    $mysqldumpPaths = [
        'mysqldump',
        'C:/xampp/mysql/bin/mysqldump.exe',
        'C:/xampp/mysql/bin/mysqldump',
        'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe',
        'C:/Program Files/MySQL/MySQL Server 5.7/bin/mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
        '/opt/lampp/bin/mysqldump',
        '/Applications/XAMPP/xamppfiles/bin/mysqldump'
    ];
    
    $mysqldump = null;
    foreach ($mysqldumpPaths as $path) {
        if (file_exists($path) || (PHP_OS_FAMILY !== 'Windows' && is_executable($path)) || $path === 'mysqldump') {
            // Test if command works
            $testCmd = $path . ' --version 2>&1';
            if (PHP_OS_FAMILY === 'Windows') {
                $testCmd = $path . ' --version 2>nul';
            }
            exec($testCmd, $testOutput, $testReturn);
            if ($testReturn === 0) {
                $mysqldump = $path;
                break;
            }
        }
    }
    
    if (!$mysqldump) {
        return ['success' => false, 'message' => 'mysqldump not found. Please install or use PHP backup method.'];
    }
    
    $command = sprintf(
        '"%s" --host=%s --user=%s --password=%s %s > "%s" 2>&1',
        $mysqldump,
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        $filepath
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        // Compress file
        $zipPath = $filepath . '.gz';
        $content = file_get_contents($filepath);
        $fp = gzopen($zipPath, 'w9');
        gzwrite($fp, $content);
        gzclose($fp);
        
        // Delete uncompressed file
        unlink($filepath);
        
        return ['success' => true, 'filename' => $filename . '.gz', 'path' => $zipPath, 'method' => 'mysqldump'];
    }
    
    return ['success' => false, 'message' => 'Failed to create backup. Error: ' . implode(' ', $output)];
}

// ============================================
// Handle backup creation
// ============================================
if (isset($_POST['create_backup'])) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        // Try PHP method first (always works)
        $result = backupDatabasePHP($pdo, $backupDir);
        
        if ($result['success']) {
            $message = 'Backup created successfully using PHP method!';
            Security::logEvent($_SESSION['user_id'], 'CREATE_BACKUP', "Created backup: {$result['filename']}");
            
            // Refresh backup list
            $backups = glob($backupDir . '*.sql.gz');
            rsort($backups);
        } else {
            // Try mysqldump method as fallback
            $result = backupDatabaseMysqldump($backupDir);
            if ($result['success']) {
                $message = 'Backup created successfully using mysqldump!';
                Security::logEvent($_SESSION['user_id'], 'CREATE_BACKUP', "Created backup: {$result['filename']}");
                
                // Refresh backup list
                $backups = glob($backupDir . '*.sql.gz');
                rsort($backups);
            } else {
                $error = 'Failed to create backup: ' . $result['message'];
            }
        }
    }
}

// Handle backup download
if (isset($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filepath = $backupDir . $filename;
    
    if (file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

// Handle backup deletion
if (isset($_GET['delete'])) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $filename = basename($_GET['delete']);
        $filepath = $backupDir . $filename;
        
        if (file_exists($filepath)) {
            unlink($filepath);
            $message = 'Backup deleted: ' . $filename;
            Security::logEvent($_SESSION['user_id'], 'DELETE_BACKUP', "Deleted backup: {$filename}");
            
            // Refresh backup list
            $backups = glob($backupDir . '*.sql.gz');
            rsort($backups);
        }
    }
}

// Handle restore
if (isset($_GET['restore'])) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        $filename = basename($_GET['restore']);
        $filepath = $backupDir . $filename;
        
        if (file_exists($filepath)) {
            $error = 'Restore functionality is disabled for security. Please use phpMyAdmin.';
        }
    }
}

// Get backup stats
$totalBackups = count($backups);
$totalSize = 0;
foreach ($backups as $backup) {
    $totalSize += filesize($backup);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <style>
        .backup-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .backup-card .icon-large {
            font-size: 60px;
            opacity: 0.2;
        }
        .backup-method {
            font-size: 12px;
            padding: 2px 10px;
            border-radius: 12px;
            background: #e9ecef;
        }
        .backup-method.php { background: #d4edda; color: #155724; }
        .backup-method.mysqldump { background: #cce5ff; color: #004085; }
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
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <?php include '../../includes/admin_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-database"></i> Database Backup</h5>
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
        
        <div class="row g-4">
            <!-- Create Backup -->
            <div class="col-md-4">
                <div class="backup-card text-center">
                    <div class="icon-large"><i class="fas fa-database text-primary"></i></div>
                    <h5>Create New Backup</h5>
                    <p class="text-muted">Creates a full backup of the database</p>
                    <div class="mb-2">
                        <span class="backup-method php">PHP Method</span>
                        <span class="text-muted small ms-1">(Always works)</span>
                    </div>
                    <form method="POST">
                        <?php echo Security::csrfField(); ?>
                        <button type="submit" name="create_backup" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-plus"></i> Create Backup Now
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="col-md-8">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="number text-primary"><?php echo $totalBackups; ?></div>
                            <div class="label">Total Backups</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="number text-success"><?php echo formatFileSize($totalSize); ?></div>
                            <div class="label">Total Size</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="number text-info"><?php echo DB_NAME; ?></div>
                            <div class="label">Database Name</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Backup List -->
        <div class="table-container mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6><i class="fas fa-list me-2"></i> Available Backups</h6>
                <?php if ($totalBackups > 0): ?>
                    <span class="badge bg-primary"><?php echo $totalBackups; ?> backups</span>
                <?php endif; ?>
            </div>
            
            <?php if (empty($backups)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-4x mb-3"></i>
                    <p>No backups found. Click "Create Backup Now" to create your first backup.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                            <?php 
                            $filename = basename($backup);
                            $ext = pathinfo($filename, PATHINFO_EXTENSION);
                            ?>
                            <tr>
                                <td>
                                    <i class="fas fa-<?php echo $ext == 'gz' ? 'file-archive' : 'file'; ?> text-warning me-2"></i>
                                    <?php echo htmlspecialchars($filename); ?>
                                    <?php if ($ext == 'gz'): ?>
                                        <span class="badge bg-secondary">Compressed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatFileSize(filesize($backup)); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', filemtime($backup)); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?download=<?php echo urlencode($filename); ?>" 
                                           class="btn btn-outline-success">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="?delete=<?php echo urlencode($filename); ?>&csrf_token=<?php echo Security::generateCSRFToken(); ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Delete this backup?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Information -->
        <div class="backup-card mt-4">
            <h6><i class="fas fa-info-circle me-2"></i> Backup Information</h6>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Location:</strong> <code><?php echo $backupDir; ?></code></p>
                    <p><strong>Format:</strong> SQL with GZIP compression</p>
                    <p><strong>Method:</strong> PHP-based (no external dependencies)</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Database:</strong> <?php echo DB_NAME; ?></p>
                    <p><strong>Encoding:</strong> UTF-8</p>
                    <p class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i> 
                        Backups are stored in the 'backups' folder. It's recommended to download and store backups off-site.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
    ?>
</body>
</html>