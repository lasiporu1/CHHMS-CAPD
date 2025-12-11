<?php
// Database Schema Creation Script
// Run this file once to create all tables

include 'config/db.php';

// Create Users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    user_role ENUM('Admin', 'Doctor', 'Nursing Officer', 'Receptionist') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Create Doctors table
$sql_doctors = "CREATE TABLE IF NOT EXISTS doctors (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
)";

// Create Nursing Officers table
$sql_nursing = "CREATE TABLE IF NOT EXISTS nursing_officers (
    nursing_id INT AUTO_INCREMENT PRIMARY KEY,
    nursing_name VARCHAR(100) NOT NULL,
    grade VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
)";

// Create Patients table
$sql_patients = "CREATE TABLE IF NOT EXISTS patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    calling_name VARCHAR(50) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    nic VARCHAR(20) UNIQUE NOT NULL,
    hospital_number VARCHAR(20) UNIQUE,
    clinic_number VARCHAR(20),
    date_of_birth DATE NOT NULL,
    sex ENUM('Male', 'Female', 'Other') NOT NULL,
    blood_group VARCHAR(5),
    contact_number VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    guardian_name VARCHAR(100),
    guardian_contact_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Create Laboratory Services table
$sql_laboratory = "CREATE TABLE IF NOT EXISTS laboratory_services (
    lab_id INT AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(100) NOT NULL,
    lab_location VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Execute all queries
if ($conn->query($sql_users) === TRUE) {
    echo "Users table created successfully<br>";
} else {
    echo "Error creating Users table: " . $conn->error . "<br>";
}

if ($conn->query($sql_doctors) === TRUE) {
    echo "Doctors table created successfully<br>";
} else {
    echo "Error creating Doctors table: " . $conn->error . "<br>";
}

if ($conn->query($sql_nursing) === TRUE) {
    echo "Nursing Officers table created successfully<br>";
} else {
    echo "Error creating Nursing Officers table: " . $conn->error . "<br>";
}

if ($conn->query($sql_patients) === TRUE) {
    echo "Patients table created successfully<br>";
} else {
    echo "Error creating Patients table: " . $conn->error . "<br>";
}

if ($conn->query($sql_laboratory) === TRUE) {
    echo "Laboratory Services table created successfully<br>";
} else {
    echo "Error creating Laboratory Services table: " . $conn->error . "<br>";
}

// Create a default admin user (username: admin, password: admin123)
$admin_username = 'admin';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$admin_email = 'admin@hospital.com';
$admin_role = 'Admin';

$sql_admin = "INSERT INTO users (username, password, email, user_role) 
              SELECT '$admin_username', '$admin_password', '$admin_email', '$admin_role'
              WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = '$admin_username')";

if ($conn->query($sql_admin) === TRUE) {
    echo "Default admin user created (username: admin, password: admin123)<br>";
} else {
    echo "Admin user might already exist or error: " . $conn->error . "<br>";
}

$conn->close();
echo "<br>Database setup complete! Please delete this file for security.";
?>
