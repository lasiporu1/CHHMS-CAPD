<?php
/**
 * Woard & Clinic Management System
 * Configuration File
 * 
 * Edit this file with your specific settings
 */

// ====================================
// DATABASE CONFIGURATION
// ====================================

// MySQL Server Details
define('DB_HOST', 'localhost');        // Database host (usually localhost)
define('DB_USER', 'root');             // MySQL username
define('DB_PASSWORD', '');             // MySQL password
define('DB_NAME', 'intimate_hospital_management');  // Database name

// ====================================
// APPLICATION SETTINGS
// ====================================

// Application Title
define('APP_TITLE', 'Woard & Clinic Management System');

// Application Version
define('APP_VERSION', '1.0');

// Application URL (without trailing slash)
define('APP_URL', 'http://localhost/CHHMS');

// ====================================
// SESSION CONFIGURATION
// ====================================

// Session Timeout (in minutes)
define('SESSION_TIMEOUT', 30);

// Cookie Settings
define('COOKIE_SECURE', false);         // Set to true if using HTTPS
define('COOKIE_HTTPONLY', true);        // Prevent JavaScript access to cookies

// ====================================
// SECURITY SETTINGS
// ====================================

// Enable Debug Mode (set to false in production)
define('DEBUG_MODE', true);

// Password Requirements
define('MIN_PASSWORD_LENGTH', 6);
define('MAX_PASSWORD_LENGTH', 50);

// Login Attempts
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_TIMEOUT', 15);    // in minutes

// ====================================
// FILE UPLOAD SETTINGS
// ====================================

// Maximum File Upload Size (in MB)
define('MAX_UPLOAD_SIZE', 5);

// Allowed File Extensions
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,pdf,doc,docx');

// Upload Directory
define('UPLOAD_DIR', dirname(__FILE__) . '/uploads/');

// ====================================
// EMAIL CONFIGURATION (Future Use)
// ====================================

// Email Settings
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM_NAME', 'Hospital Management System');
define('MAIL_FROM_EMAIL', 'noreply@hospital.com');

// Enable Email (set to false if not configured)
define('ENABLE_EMAIL', false);

// ====================================
// TIMEZONE CONFIGURATION
// ====================================

// Application Timezone
date_default_timezone_set('UTC');       // Change to your timezone
// Examples: 'Asia/Colombo', 'Asia/Bangkok', 'America/New_York', 'Europe/London'

// ====================================
// ROLE-BASED PERMISSIONS
// ====================================

// User Roles and Permissions
$ROLE_PERMISSIONS = array(
    'Admin' => array(
        'manage_users' => true,
        'manage_doctors' => true,
        'manage_nursing' => true,
        'manage_patients' => true,
        'manage_laboratory' => true,
        'view_reports' => true,
        'system_settings' => true
    ),
    'Doctor' => array(
        'manage_users' => false,
        'manage_doctors' => false,
        'manage_nursing' => false,
        'manage_patients' => true,
        'manage_laboratory' => true,
        'view_reports' => true,
        'system_settings' => false
    ),
    'Nursing Officer' => array(
        'manage_users' => false,
        'manage_doctors' => false,
        'manage_nursing' => false,
        'manage_patients' => true,
        'manage_laboratory' => false,
        'view_reports' => false,
        'system_settings' => false
    ),
    'Receptionist' => array(
        'manage_users' => false,
        'manage_doctors' => false,
        'manage_nursing' => false,
        'manage_patients' => true,
        'manage_laboratory' => false,
        'view_reports' => false,
        'system_settings' => false
    )
);

// ====================================
// DATE & TIME FORMATS
// ====================================

// Date Format
define('DATE_FORMAT', 'Y-m-d');         // 2024-12-25
define('DATE_TIME_FORMAT', 'Y-m-d H:i:s'); // 2024-12-25 14:30:45

// Display Format
define('DISPLAY_DATE_FORMAT', 'F d, Y');        // December 25, 2024
define('DISPLAY_DATETIME_FORMAT', 'F d, Y g:i A'); // December 25, 2024 2:30 PM

// ====================================
// BLOOD GROUP OPTIONS
// ====================================

$BLOOD_GROUPS = array(
    'O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'
);

// ====================================
// NURSING GRADES
// ====================================

$NURSING_GRADES = array(
    'Grade I',
    'Grade II',
    'Grade III',
    'Senior Grade'
);

// ====================================
// LABORATORY REPORT TYPES
// ====================================

$REPORT_TYPES = array(
    'Blood Test',
    'X-Ray',
    'Ultrasound',
    'CT Scan',
    'ECG',
    'Urine Test',
    'MRI',
    'Other'
);

// ====================================
// GENDER OPTIONS
// ====================================

$GENDER_OPTIONS = array(
    'Male',
    'Female',
    'Other'
);

// ====================================
// ERROR MESSAGES
// ====================================

$ERROR_MESSAGES = array(
    'DB_CONNECTION_FAILED' => 'Database connection failed. Please contact administrator.',
    'INVALID_CREDENTIALS' => 'Invalid username or password.',
    'SESSION_EXPIRED' => 'Your session has expired. Please login again.',
    'PERMISSION_DENIED' => 'You do not have permission to perform this action.',
    'INVALID_INPUT' => 'Please provide valid input.',
    'RECORD_NOT_FOUND' => 'Record not found.',
    'DUPLICATE_RECORD' => 'This record already exists.',
    'OPERATION_FAILED' => 'Operation failed. Please try again.'
);

// ====================================
// SUCCESS MESSAGES
// ====================================

$SUCCESS_MESSAGES = array(
    'RECORD_CREATED' => 'Record created successfully!',
    'RECORD_UPDATED' => 'Record updated successfully!',
    'RECORD_DELETED' => 'Record deleted successfully!',
    'LOGIN_SUCCESS' => 'Login successful!',
    'PASSWORD_CHANGED' => 'Password changed successfully!'
);

// ====================================
// PAGINATION SETTINGS
// ====================================

// Records per page
define('RECORDS_PER_PAGE', 10);

// ====================================
// LOGGING CONFIGURATION
// ====================================

// Enable Activity Logging (future feature)
define('ENABLE_LOGGING', false);

// Log File Path
define('LOG_FILE', dirname(__FILE__) . '/logs/activity.log');

// Log Level: 'ALL', 'INFO', 'ERROR', 'WARNING'
define('LOG_LEVEL', 'ERROR');

?>
