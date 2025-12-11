<?php
include '../../config/db.php';

// Set proper headers
header('Content-Type: application/json');

// Check if this is an AJAX request for patient search
if (isset($_GET['search_term']) && !empty($_GET['search_term'])) {
    try {
        $search_term = $conn->real_escape_string($_GET['search_term']);
        
        // Simple search query
        $search_sql = "SELECT patient_id, calling_name, full_name, nic, hospital_number, clinic_number, contact_number
                       FROM patients 
                       WHERE calling_name LIKE '%$search_term%' 
                          OR full_name LIKE '%$search_term%' 
                          OR nic LIKE '%$search_term%'
                       ORDER BY calling_name 
                       LIMIT 10";
        
        $search_result = $conn->query($search_sql);
        $patients = array();
        
        if ($search_result) {
            while ($row = $search_result->fetch_assoc()) {
                $patients[] = array(
                    'patient_id' => (int)$row['patient_id'],
                    'calling_name' => $row['calling_name'] ?? '',
                    'full_name' => $row['full_name'] ?? '',
                    'nic' => $row['nic'] ?? '',
                    'hospital_number' => $row['hospital_number'] ?? '',
                    'clinic_number' => $row['clinic_number'] ?? '',
                    'contact_number' => $row['contact_number'] ?? ''
                );
            }
        }
        
        echo json_encode($patients);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No search term provided']);
}
?>