<?php
// Patient Search AJAX Endpoint
// This file handles autocomplete search requests for patient selection across the system

include '../config/db.php';

// Ensure this is an AJAX request
if (!isset($_GET['search_patients']) || empty($_GET['search_term'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

try {
    $search_term = $conn->real_escape_string($_GET['search_term']);
    
    // Comprehensive search query across all patient fields
     $search_sql = "SELECT p.patient_id, p.calling_name, p.full_name, p.nic, 
                                  p.hospital_number, p.clinic_number, p.contact_number,
                                  no.nursing_name,
                                  DATE_FORMAT(p.date_of_birth, '%Y-%m-%d') as date_of_birth,
                                  TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
                         FROM patients p
                         LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id
                         WHERE (p.calling_name LIKE '%$search_term%' 
                             OR p.full_name LIKE '%$search_term%' 
                             OR p.nic LIKE '%$search_term%' 
                             OR p.hospital_number LIKE '%$search_term%' 
                             OR p.clinic_number LIKE '%$search_term%'
                             OR p.contact_number LIKE '%$search_term%'
                             OR no.nursing_name LIKE '%$search_term%')
                            AND (p.patient_status IS NULL OR p.patient_status = '' OR p.patient_status != 'Deceased')
                         ORDER BY p.calling_name LIMIT 15";
                   
    $search_result = $conn->query($search_sql);
    
    if (!$search_result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $patients = array();
    while ($row = $search_result->fetch_assoc()) {
        // Ensure all fields have values to prevent null issues
        $row['hospital_number'] = $row['hospital_number'] ?: '';
        $row['contact_number'] = $row['contact_number'] ?: '';
        $row['nursing_name'] = $row['nursing_name'] ?: '';
        $patients[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($patients);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>