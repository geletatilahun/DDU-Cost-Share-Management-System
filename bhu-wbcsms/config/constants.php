<?php
// ============================================
// SYSTEM CONSTANTS
// ============================================

// User Roles
define('ROLE_ADMIN', 1);
define('ROLE_OFFICER', 2);
define('ROLE_HOD', 3);
define('ROLE_FINANCE', 4);
define('ROLE_STUDENT', 5);

// Application Status
define('STATUS_PENDING', 'pending');
define('STATUS_OFFICER_APPROVED', 'officer_approved');
define('STATUS_HOD_APPROVED', 'hod_approved');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_DISBURSED', 'disbursed');

// Payment Status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_PARTIAL', 'partial');
define('PAYMENT_PAID', 'paid');
define('PAYMENT_VERIFIED', 'verified');
define('PAYMENT_DISBURSED', 'disbursed');

// Feedback Categories
define('FEEDBACK_GENERAL', 'general');
define('FEEDBACK_COMPLAINT', 'complaint');
define('FEEDBACK_SUGGESTION', 'suggestion');
define('FEEDBACK_INQUIRY', 'inquiry');

// Notice Types
define('NOTICE_GENERAL', 'general');
define('NOTICE_DEADLINE', 'deadline');
define('NOTICE_PAYMENT', 'payment');
define('NOTICE_DISBURSEMENT', 'disbursement');
define('NOTICE_ALERT', 'alert');
?>