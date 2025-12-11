<?php
// Simple test page to access the Patient History Detail Report
include '../../config/db.php';

// Get first patient ID from database for testing
$test_query = "SELECT patient_id, calling_name FROM patients LIMIT 1";
$result = $conn->query($test_query);

if ($result && $result->num_rows > 0) {
    $patient = $result->fetch_assoc();
    $patient_id = $patient['patient_id'];
    $patient_name = $patient['calling_name'];
    
    echo "<h2>Test Patient History Detail Report</h2>";
    echo "<p>Testing with Patient: " . htmlspecialchars($patient_name) . " (ID: " . $patient_id . ")</p>";
    echo "<p><a href='patient_history_detail_report.php?patient_id=" . $patient_id . "' target='_blank'>Click here to view Patient History Detail Report</a></p>";
    echo "<p><a href='patient_search.php'>Back to Patient Search</a></p>";
    
    // Also show all available patients
    echo "<h3>Available Patients:</h3>";
    $all_patients = "SELECT patient_id, calling_name, clinic_number FROM patients ORDER BY calling_name LIMIT 10";
    $all_result = $conn->query($all_patients);
    
    if ($all_result && $all_result->num_rows > 0) {
        echo "<ul>";
        while ($pat = $all_result->fetch_assoc()) {
            echo "<li><a href='patient_history_detail_report.php?patient_id=" . $pat['patient_id'] . "'>" . 
                 htmlspecialchars($pat['calling_name']) . " (Clinic: " . $pat['clinic_number'] . ")</a></li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No patients found in database.</p>";
    }
} else {
    echo "<h2>No patients found in database</h2>";
    echo "<p>Please check if there are patients in the database.</p>";
    echo "<p><a href='patient_search.php'>Back to Patient Search</a></p>";
}
?>