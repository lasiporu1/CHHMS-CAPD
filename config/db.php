<?php
// Database Connection Configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
if (!defined('DB_NAME')) define('DB_NAME', 'intimate_hospital_management');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");

// Activity logging helper – uses the existing $conn
if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $table_name, $record_id = null, $details = null) {
        global $conn;
        // ensure table exists
        $create_log = "CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(50) NOT NULL,
            table_name VARCHAR(100) NOT NULL,
            record_id VARCHAR(50) NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($create_log);

        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $uid = $user_id !== null ? (string)$user_id : '';
            $rid = $record_id !== null ? (string)$record_id : '';
            $det = $details !== null ? (string)$details : '';
            $stmt->bind_param('sssss', $uid, $action, $table_name, $rid, $det);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Intentionally omit closing PHP tag to avoid accidental whitespace output
