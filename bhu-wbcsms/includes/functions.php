<?php
// ============================================
// GENERAL HELPER FUNCTIONS
// ============================================

// Redirect
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Display success message
function showSuccess($message) {
    return '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> ' . htmlspecialchars($message) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

// Display error message
function showError($message) {
    return '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($message) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

// Display warning message
function showWarning($message) {
    return '<div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($message) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

// Display info message
function showInfo($message) {
    return '<div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle"></i> ' . htmlspecialchars($message) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

// Format currency
function formatCurrency($amount) {
    return 'ETB ' . number_format($amount, 2);
}

// Format date
function formatDate($date, $format = 'M d, Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Format datetime
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

// Get time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
}

// Upload file
function uploadFile($file, $targetDir = 'uploads/', $allowedTypes = ALLOWED_EXTENSIONS) {
    if (!isset($file) || $file['error'] !== 0) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds limit (5MB)'];
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed. Allowed: ' . implode(', ', $allowedTypes)];
    }
    
    // Create directory if doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = time() . '_' . generateRandomString(8) . '.' . $extension;
    $targetPath = $targetDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

// Get user role name
function getRoleName($roleId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt->execute([$roleId]);
    $result = $stmt->fetch();
    return $result ? $result['role_name'] : 'Unknown';
}

// Get status badge
function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'officer_approved' => 'info',
        'hod_approved' => 'primary',
        'approved' => 'success',
        'rejected' => 'danger',
        'disbursed' => 'dark',
        'paid' => 'success',
        'verified' => 'success',
        'active' => 'success',
        'inactive' => 'secondary'
    ];
    
    $class = $badges[$status] ?? 'secondary';
    return '<span class="badge bg-' . $class . '">' . str_replace('_', ' ', ucfirst($status)) . '</span>';
}

// Send email using PHPMailer
function sendEmail($to, $subject, $body, $isHTML = true) {
    require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';
    require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>