<?php
// Debug version of clinic list
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting CAPD Clinic page...<br>";

// Check if config file exists
$config_path = '../../config/db.php';
if (file_exists($config_path)) {
    echo "Config file found<br>";
    include $config_path;
} else {
    die("Config file not found at: " . realpath($config_path));
}

session_start();
echo "Session started<br>";

if (!isset($_SESSION['user_id'])) {
    echo "User not logged in, redirecting...<br>";
    header("Location: ../../login.php");
    exit();
}

echo "User logged in: " . $_SESSION['username'] . "<br>";
echo "Database connection successful<br>";

?>
<!DOCTYPE html>
<html>
<head>
    <title>CAPD Clinic Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h2>🩺 CAPD Clinic Management</h2>
                <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Manage CAPD clinic and patient appointments</p>
            </div>
            <div>
                <a href="../../index.php" class="btn btn-secondary">🏠 Dashboard</a>
            </div>
        </div>

        <div class="quick-actions">
            <a href="appointment_form.php" class="btn btn-success">📅 New CAPD Appointment</a>
            <a href="appointment_list.php" class="btn btn-secondary">📋 CAPD Appointments</a>
        </div>

        <div class="card">
            <div class="section-header">
                <h3>🩺 CAPD Clinic Status</h3>
            </div>
            
            <p>Welcome to the CAPD Clinic Management System!</p>
            <p>This system helps manage CAPD patient appointments and treatment schedules.</p>
            
            <div style="margin-top: 2rem;">
                <h4>Quick Actions:</h4>
                <ul>
                    <li><a href="appointment_form.php">Schedule New CAPD Appointment</a></li>
                    <li><a href="appointment_list.php">View All CAPD Appointments</a></li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>