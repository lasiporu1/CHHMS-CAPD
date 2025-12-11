<?php
// Add patient_status field to patients table and death_date field
include '../../config/db.php';

// Check if patient_status column exists
$check_status = "SHOW COLUMNS FROM patients LIKE 'patient_status'";
$status_exists = $conn->query($check_status);

if ($status_exists->num_rows == 0) {
    $alter_status = "ALTER TABLE patients 
                     ADD COLUMN patient_status ENUM('Active', 'Deceased', 'Inactive') DEFAULT 'Active' AFTER assigned_nursing_officer";
    if ($conn->query($alter_status)) {
        echo "✓ Added patient_status column to patients table<br>";
    } else {
        echo "✗ Error adding patient_status: " . $conn->error . "<br>";
    }
} else {
    echo "✓ patient_status column already exists<br>";
}

// Check if death_date column exists
$check_death = "SHOW COLUMNS FROM patients LIKE 'death_date'";
$death_exists = $conn->query($check_death);

if ($death_exists->num_rows == 0) {
    $alter_death = "ALTER TABLE patients 
                    ADD COLUMN death_date DATE NULL AFTER patient_status,
                    ADD COLUMN death_notes TEXT NULL AFTER death_date";
    if ($conn->query($alter_death)) {
        echo "✓ Added death_date and death_notes columns to patients table<br>";
    } else {
        echo "✗ Error adding death columns: " . $conn->error . "<br>";
    }
} else {
    echo "✓ death_date column already exists<br>";
}

// Update discharge_status enum to include Death
$check_discharge = "SHOW COLUMNS FROM ward_admissions LIKE 'discharge_status'";
$discharge_result = $conn->query($check_discharge);

if ($discharge_result->num_rows > 0) {
    $row = $discharge_result->fetch_assoc();
    if (strpos($row['Type'], 'Death') === false) {
        $alter_discharge = "ALTER TABLE ward_admissions 
                           MODIFY COLUMN discharge_status ENUM('Complete', 'Pending', 'Death') DEFAULT 'Complete'";
        if ($conn->query($alter_discharge)) {
            echo "✓ Updated discharge_status to include 'Death' option<br>";
        } else {
            echo "✗ Error updating discharge_status: " . $conn->error . "<br>";
        }
    } else {
        echo "✓ discharge_status already includes 'Death' option<br>";
    }
}

echo "<br>Migration completed!";
$conn->close();
?>
