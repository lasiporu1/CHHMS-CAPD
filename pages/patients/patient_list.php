<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Create PET table if it doesn't exist
$create_pet_table = "CREATE TABLE IF NOT EXISTS peritoneal_equilibration_test (
    pet_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    test_date DATE NOT NULL,
    pet_level ENUM('High', 'High Average', 'Low Average', 'Low') NOT NULL,
    d_p_creatinine DECIMAL(5,2) NULL,
    d_d0_glucose DECIMAL(5,2) NULL,
    ultrafiltration INT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    INDEX idx_patient_date (patient_id, test_date)
)";
$conn->query($create_pet_table);

// Create CAPD Status table if it doesn't exist
$create_capd_table = "CREATE TABLE IF NOT EXISTS capd_status (
    capd_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    catheter_insertion_date DATE NOT NULL,
    surgeon_doctor_id INT NOT NULL,
    capd_start_date DATE NULL,
    nursing_officer_id INT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (surgeon_doctor_id) REFERENCES doctors(doctor_id) ON DELETE RESTRICT,
    FOREIGN KEY (nursing_officer_id) REFERENCES nursing_officers(nursing_id) ON DELETE SET NULL,
    UNIQUE KEY unique_patient_catheter (patient_id, catheter_insertion_date),
    INDEX idx_patient_date (patient_id, catheter_insertion_date)
)";
$conn->query($create_capd_table);

// Handle PET form submission
$pet_message = '';
$pet_message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_pet') {
    $patient_id = (int)$_POST['patient_id'];
    $test_date = $conn->real_escape_string($_POST['test_date']);
    $pet_level = $conn->real_escape_string($_POST['pet_level']);
    $d_p_creatinine = !empty($_POST['d_p_creatinine']) ? (float)$_POST['d_p_creatinine'] : 'NULL';
    $d_d0_glucose = !empty($_POST['d_d0_glucose']) ? (float)$_POST['d_d0_glucose'] : 'NULL';
    $ultrafiltration = !empty($_POST['ultrafiltration']) ? (int)$_POST['ultrafiltration'] : 'NULL';
    $notes = $conn->real_escape_string($_POST['notes']);
    $created_by = $_SESSION['user_id'];
    
    if (is_numeric($d_p_creatinine)) {
        $d_p_creatinine = "'$d_p_creatinine'";
    }
    if (is_numeric($d_d0_glucose)) {
        $d_d0_glucose = "'$d_d0_glucose'";
    }
    
    $insert_sql = "INSERT INTO peritoneal_equilibration_test 
                   (patient_id, test_date, pet_level, d_p_creatinine, d_d0_glucose, ultrafiltration, notes, created_by) 
                   VALUES ($patient_id, '$test_date', '$pet_level', $d_p_creatinine, $d_d0_glucose, $ultrafiltration, '$notes', $created_by)";
    
    if ($conn->query($insert_sql)) {
        $pet_message = "PET record added successfully!";
        $pet_message_type = 'success';
    } else {
        $pet_message = "Error adding PET record: " . $conn->error;
        $pet_message_type = 'error';
    }
}

// Get all PET records for overview
$all_pet_records = [];
if (isset($_GET['view_all_pet'])) {
    $all_pet_query = "SELECT 
                        pet.*, 
                        p.calling_name, 
                        p.full_name, 
                        p.nic,
                        p.hospital_number,
                        p.clinic_number,
                        u.username as created_by_name
                      FROM peritoneal_equilibration_test pet
                      LEFT JOIN patients p ON pet.patient_id = p.patient_id
                      LEFT JOIN users u ON pet.created_by = u.user_id
                      ORDER BY pet.test_date DESC, pet.created_at DESC";
    $all_pet_result = $conn->query($all_pet_query);
    if ($all_pet_result) {
        while ($row = $all_pet_result->fetch_assoc()) {
            $all_pet_records[] = $row;
        }
    }
}

// Get PET records for selected patient
$pet_records = [];
if (isset($_GET['view_pet']) && !empty($_GET['view_pet'])) {
    $view_patient_id = (int)$_GET['view_pet'];
    $pet_query = "SELECT pet.*, p.calling_name, p.full_name, p.nic, u.username as created_by_name
                  FROM peritoneal_equilibration_test pet
                  LEFT JOIN patients p ON pet.patient_id = p.patient_id
                  LEFT JOIN users u ON pet.created_by = u.user_id
                  WHERE pet.patient_id = $view_patient_id
                  ORDER BY pet.test_date DESC";
    $pet_result = $conn->query($pet_query);
    if ($pet_result) {
        while ($row = $pet_result->fetch_assoc()) {
            $pet_records[] = $row;
        }
    }
}

// Delete patient if requested
if (isset($_GET['delete'])) {
    $id = $conn->real_escape_string($_GET['delete']);
    $sql = "DELETE FROM patients WHERE patient_id = $id";
    if ($conn->query($sql) === TRUE) {
        header("Location: patient_list.php");
        exit();
    }
}

// Get last PET record for a patient (AJAX)
if (isset($_GET['get_last_pet']) && !empty($_GET['patient_id'])) {
    $patient_id = (int)$_GET['patient_id'];
    $pet_sql = "SELECT pet_level, test_date 
                FROM peritoneal_equilibration_test 
                WHERE patient_id = $patient_id 
                ORDER BY test_date DESC, created_at DESC 
                LIMIT 1";
    $pet_result = $conn->query($pet_sql);
    
    $response = ['has_record' => false];
    if ($pet_result && $pet_result->num_rows > 0) {
        $pet_data = $pet_result->fetch_assoc();
        $response = [
            'has_record' => true,
            'pet_level' => $pet_data['pet_level'],
            'test_date' => $pet_data['test_date']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Handle CAPD form submission
$capd_message = '';
$capd_message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_capd') {
    $patient_id = (int)$_POST['patient_id'];
    $catheter_insertion_date = $conn->real_escape_string($_POST['catheter_insertion_date']);
    $surgeon_doctor_id = (int)$_POST['surgeon_doctor_id'];
    // $nursing_officer_id = !empty($_POST['nursing_officer_id']) ? (int)$_POST['nursing_officer_id'] : NULL; // TODO: Enable when DB column exists
    $capd_start_date = !empty($_POST['capd_start_date']) ? $conn->real_escape_string($_POST['capd_start_date']) : NULL;
    $notes = $conn->real_escape_string($_POST['notes']);
    $created_by = $_SESSION['user_id'];
    
    // Check if record exists for this patient and catheter date
    $check_sql = "SELECT capd_id FROM capd_status WHERE patient_id = $patient_id AND catheter_insertion_date = '$catheter_insertion_date'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result && $check_result->num_rows > 0) {
        // Update existing record
        $existing = $check_result->fetch_assoc();
        
        if ($capd_start_date !== NULL) {
            $update_sql = "UPDATE capd_status 
                           SET surgeon_doctor_id = $surgeon_doctor_id, 
                               capd_start_date = '$capd_start_date', 
                               notes = '$notes',
                               updated_at = CURRENT_TIMESTAMP
                           WHERE capd_id = {$existing['capd_id']}";
        } else {
            $update_sql = "UPDATE capd_status 
                           SET surgeon_doctor_id = $surgeon_doctor_id, 
                               capd_start_date = NULL, 
                               notes = '$notes',
                               updated_at = CURRENT_TIMESTAMP
                           WHERE capd_id = {$existing['capd_id']}";
        }
        
        if ($conn->query($update_sql)) {
            $capd_message = "CAPD Status record updated successfully!";
            $capd_message_type = 'success';
        } else {
            $capd_message = "Error updating CAPD Status record: " . $conn->error;
            $capd_message_type = 'error';
        }
    } else {
        // Insert new record
        if ($capd_start_date !== NULL) {
            // Include CAPD start date if provided
            $insert_sql = "INSERT INTO capd_status 
                           (patient_id, catheter_insertion_date, surgeon_doctor_id, capd_start_date, notes, created_by) 
                           VALUES ($patient_id, '$catheter_insertion_date', $surgeon_doctor_id, '$capd_start_date', '$notes', $created_by)";
        } else {
            // Omit CAPD start date if not provided
            $insert_sql = "INSERT INTO capd_status 
                           (patient_id, catheter_insertion_date, surgeon_doctor_id, notes, created_by) 
                           VALUES ($patient_id, '$catheter_insertion_date', $surgeon_doctor_id, '$notes', $created_by)";
        }
        
        if ($conn->query($insert_sql)) {
            $capd_message = "CAPD Status record added successfully!";
            $capd_message_type = 'success';
        } else {
            $capd_message = "Error adding CAPD Status record: " . $conn->error;
            $capd_message_type = 'error';
        }
    }
}

// Handle CAPD edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_capd') {
    $capd_id = (int)$_POST['capd_id'];
    $catheter_insertion_date = $conn->real_escape_string($_POST['catheter_insertion_date']);
    $surgeon_doctor_id = (int)$_POST['surgeon_doctor_id'];
    // $nursing_officer_id = !empty($_POST['nursing_officer_id']) ? (int)$_POST['nursing_officer_id'] : NULL; // TODO: Enable when DB column exists
    $capd_start_date = !empty($_POST['capd_start_date']) ? $conn->real_escape_string($_POST['capd_start_date']) : NULL;
    $notes = $conn->real_escape_string($_POST['notes']);
    
    if ($capd_start_date !== NULL) {
        $update_sql = "UPDATE capd_status 
                       SET catheter_insertion_date = '$catheter_insertion_date', 
                           surgeon_doctor_id = $surgeon_doctor_id, 
                           capd_start_date = '$capd_start_date', 
                           notes = '$notes',
                           updated_at = CURRENT_TIMESTAMP
                       WHERE capd_id = $capd_id";
    } else {
        $update_sql = "UPDATE capd_status 
                       SET catheter_insertion_date = '$catheter_insertion_date', 
                           surgeon_doctor_id = $surgeon_doctor_id, 
                           capd_start_date = NULL, 
                           notes = '$notes',
                           updated_at = CURRENT_TIMESTAMP
                       WHERE capd_id = $capd_id";
    }
    
    if ($conn->query($update_sql)) {
        $capd_message = "CAPD Status record updated successfully!";
        $capd_message_type = 'success';
    } else {
        $capd_message = "Error updating CAPD Status record: " . $conn->error;
        $capd_message_type = 'error';
    }
}

// Handle CAPD delete
if (isset($_GET['delete_capd'])) {
    $capd_id = (int)$_GET['delete_capd'];
    $delete_sql = "DELETE FROM capd_status WHERE capd_id = $capd_id";
    
    if ($conn->query($delete_sql)) {
        $capd_message = "CAPD Status record deleted successfully!";
        $capd_message_type = 'success';
    } else {
        $capd_message = "Error deleting CAPD Status record: " . $conn->error;
        $capd_message_type = 'error';
    }
}

// Get CAPD record for patient (AJAX)
if (isset($_GET['get_capd_data']) && !empty($_GET['patient_id'])) {
    $patient_id = (int)$_GET['patient_id'];
    $capd_sql = "SELECT cs.*, d.doctor_name, d.specialization
                 FROM capd_status cs
                 LEFT JOIN doctors d ON cs.surgeon_doctor_id = d.doctor_id
                 WHERE cs.patient_id = $patient_id
                 ORDER BY cs.catheter_insertion_date DESC
                 LIMIT 1";
    $capd_result = $conn->query($capd_sql);
    
    $response = ['has_record' => false];
    if ($capd_result && $capd_result->num_rows > 0) {
        $capd_data = $capd_result->fetch_assoc();
        $response = [
            'has_record' => true,
            'capd_id' => $capd_data['capd_id'],
            'catheter_insertion_date' => $capd_data['catheter_insertion_date'],
            'capd_start_date' => $capd_data['capd_start_date'],
            'surgeon_doctor_id' => $capd_data['surgeon_doctor_id'],
            'doctor_name' => $capd_data['doctor_name'],
            'notes' => $capd_data['notes']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get patient details for CAPD form (AJAX)
if (isset($_GET['get_patient_details']) && !empty($_GET['patient_id'])) {
    $patient_id = (int)$_GET['patient_id'];
    $patient_sql = "SELECT p.*, no.nursing_name
                    FROM patients p
                    LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id
                    WHERE p.patient_id = $patient_id";
    $patient_result = $conn->query($patient_sql);
    
    $response = [];
    if ($patient_result && $patient_result->num_rows > 0) {
        $patient_data = $patient_result->fetch_assoc();
        // Calculate age
        $birthDate = new DateTime($patient_data['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birthDate);
        $ageString = $age->y . 'y';
        if ($age->m > 0) {
            $ageString .= ' ' . $age->m . 'm';
        }
        
        $response = [
            'patient_id' => $patient_data['patient_id'],
            'calling_name' => $patient_data['calling_name'],
            'full_name' => $patient_data['full_name'],
            'age' => $ageString,
            'hospital_number' => $patient_data['hospital_number'],
            'clinic_number' => $patient_data['clinic_number'],
            'nursing_name' => $patient_data['nursing_name']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Search patients for AJAX
if (isset($_GET['search_patients']) && !empty($_GET['search_term'])) {
    $search_term = $conn->real_escape_string($_GET['search_term']);
    $search_sql = "SELECT p.patient_id, p.calling_name, p.full_name, p.nic, p.hospital_number, p.clinic_number,
                          wa.admission_id, wa.admission_status
                   FROM patients p
                   LEFT JOIN ward_admissions wa ON p.patient_id = wa.patient_id AND wa.admission_status = 'Active'
                   WHERE p.calling_name LIKE '%$search_term%' 
                      OR p.full_name LIKE '%$search_term%'
                      OR p.nic LIKE '%$search_term%' 
                      OR p.hospital_number LIKE '%$search_term%' 
                      OR p.clinic_number LIKE '%$search_term%'
                   ORDER BY p.calling_name LIMIT 10";
    $search_result = $conn->query($search_sql);
    
    $patients = array();
    while ($row = $search_result->fetch_assoc()) {
        $patients[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($patients);
    exit();
}

// Add nursing officer column if it doesn't exist
$check_column = "SHOW COLUMNS FROM patients LIKE 'assigned_nursing_officer'";
$column_exists = $conn->query($check_column);
if ($column_exists->num_rows == 0) {
    $alter_sql = "ALTER TABLE patients ADD COLUMN assigned_nursing_officer INT NULL";
    $conn->query($alter_sql);
}

// Search functionality
$search = '';
$sql = "SELECT p.*, no.nursing_name 
        FROM patients p 
        LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " WHERE p.calling_name LIKE '%$search%' OR p.full_name LIKE '%$search%' OR p.nic LIKE '%$search%' OR p.hospital_number LIKE '%$search%' OR p.clinic_number LIKE '%$search%' OR p.contact_number LIKE '%$search%' OR no.nursing_name LIKE '%$search%'";
}

$sql .= " ORDER BY CASE WHEN p.patient_status = 'Deceased' THEN 0 ELSE 1 END, created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
        }
        
        .navbar {
            background: rgba(44, 62, 80, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar h1 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background-color: #34495e;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2.5rem;
            overflow: hidden;
        }
        
        .card h2 {
            color: #2c3e50;
            margin: 0 0 2rem 0;
            font-size: 2rem;
            font-weight: 600;
            border-bottom: 3px solid #3498db;
            padding-bottom: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-info {
            background-color: #3498db;
            color: white;
            padding: 0;
            font-size: 0.9rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        table thead {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
        }
        
        table th, table td {
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.95rem;
        }
        
        table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            transform: scale(1.01);
            transition: all 0.3s ease;
        }
        
        table tbody tr.deceased-patient {
            background: linear-gradient(135deg, #ffebee, #ffcdd2) !important;
            border-left: 4px solid #d32f2f;
        }
        
        table tbody tr.deceased-patient:hover {
            background: linear-gradient(135deg, #ffcdd2, #ef9a9a) !important;
        }
        
        .deceased-badge {
            background: linear-gradient(135deg, #d32f2f, #c62828);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            margin-left: 0.5rem;
            box-shadow: 0 2px 4px rgba(211, 47, 47, 0.3);
        }
        
        .btn-group {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
            flex-wrap: nowrap;
        }
        
        .btn-sm {
            padding: 0.4rem 0.6rem;
            font-size: 0.75rem;
            min-width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Tooltip styles */
        .btn[title] {
            position: relative;
        }
        
        .btn[title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            white-space: nowrap;
            font-size: 0.875rem;
            z-index: 1000;
            margin-bottom: 5px;
        }
        
        .btn[title]:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
        }
        
        /* Column width optimizations */
        table th:nth-child(6), table td:nth-child(6) { /* DOB column */
            width: 100px;
            white-space: nowrap;
        }
        
        table th:nth-child(7), table td:nth-child(7) { /* Age column */
            width: 70px;
            text-align: center;
        }
        
        .search-box {
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
        }
        
        .search-box input {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            flex: 1;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }
        
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .search-item {
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .search-item:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Patient Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="#" onclick="openCAPDFormWithSearch(); return false;">💊 CAPD Status</a>
            <a href="#" onclick="openPETFormWithSearch(); return false;">🧪 PET Test</a>
            <a href="../admissions/death_registration.php">⚰️ Register Death</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header" style="background: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="color: #2c3e50; margin: 0; font-size: 2rem; font-weight: 600;">👥 Patient Management</h2>
                <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Manage patient records and PET test data</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="patient_form.php" class="btn btn-primary">➕ Add New Patient</a>
            </div>
        </div>
        
        <div class="search-section" style="background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <form style="margin: 0;">
                <div class="search-box" style="margin: 0;">
                    <div class="search-container" style="position: relative; flex: 1;">
                        <input type="text" id="patient_search" name="search" placeholder="🔍 Search by patient name, NIC, Hospital Number, Clinic Number..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" autocomplete="off">
                        <div class="search-results" id="search_results" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 1000; display: none;"></div>
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="patient_list.php" class="btn btn-secondary">✖️ Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="data-section" style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div class="section-header" style="margin-bottom: 1.5rem;">
                <h3 style="color: #2c3e50; margin: 0; font-size: 1.25rem; font-weight: 600;">📋 Patient Records (<?php echo $result->num_rows; ?> patients)</h3>
            </div>
            <div class="table-container" style="overflow-x: auto;">
                <?php if ($result->num_rows > 0): ?>
                    <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>NIC</th>
                            <th>Hospital #</th>
                            <th>Clinic #</th>
                            <th>DOB</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Blood Group</th>
                            <th>Contact</th>
                            <th>Nursing Officer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($patient = $result->fetch_assoc()): 
                            // Calculate age
                            $birthDate = new DateTime($patient['date_of_birth']);
                            $today = new DateTime();
                            $age = $today->diff($birthDate);
                            $ageString = $age->y . 'y';
                            if ($age->m > 0) {
                                $ageString .= ' ' . $age->m . 'm';
                            }
                            // Check if patient is deceased
                            $isDeceased = isset($patient['patient_status']) && $patient['patient_status'] == 'Deceased';
                            $rowClass = $isDeceased ? 'class="deceased-patient"' : '';
                        ?>
                            <tr <?php echo $rowClass; ?>>
                                <td><span style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600;"><?php echo $patient['patient_id']; ?></span></td>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50;"><?php echo $patient['calling_name']; ?></div>
                                    <div style="font-size: 0.875rem; color: #7f8c8d;"><?php echo $patient['full_name']; ?></div>
                                    <?php if ($isDeceased && !empty($patient['death_date'])): ?>
                                        <div style="font-size: 0.75rem; color: #d32f2f; font-weight: 600; margin-top: 0.25rem;">
                                            Death Date: <?php echo date('M j, Y', strtotime($patient['death_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span style="font-family: monospace; background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 4px;"><?php echo $patient['nic']; ?></span></td>
                                <td><?php echo $patient['hospital_number'] ? '<span style="background: #e8f5e8; color: #2d6a2d; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 500;">' . $patient['hospital_number'] . '</span>' : '<span style="color: #999;">-</span>'; ?></td>
                                <td><?php echo $patient['clinic_number'] ? '<span style="background: #e3f2fd; color: #1565c0; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 500;">' . $patient['clinic_number'] . '</span>' : '<span style="color: #999;">-</span>'; ?></td>
                                <td style="font-size: 0.875rem; color: #555;"><?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?></td>
                                <td style="text-align: center;"><span style="color: #2c3e50; font-weight: 600; font-size: 0.875rem;"><?php echo $ageString; ?></span></td>
                                <td><?php echo $patient['sex']; ?></td>
                                <td><?php echo $patient['blood_group'] ? '<span style="background: #ffebee; color: #c62828; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600;">' . $patient['blood_group'] . '</span>' : '<span style="color: #999;">-</span>'; ?></td>
                                <td><span style="color: #2c3e50; font-weight: 500;"><?php echo $patient['contact_number']; ?></span></td>
                                <td>
                                    <?php if ($patient['nursing_name']): ?>
                                        <span style="background: linear-gradient(135deg, #e8f5e8, #c3e6cb); color: #2d6a2d; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($patient['nursing_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic;">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="patient_view.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-info btn-sm" title="View">👁️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: #6c757d;">
                    <h3>👥 No Patients Found</h3>
                    <p style="margin: 1rem 0;">Start by registering your first patient.</p>
                    <a href="patient_form.php" class="btn btn-primary" style="margin-top: 1rem;">➕ Register First Patient</a>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('patient_search');
            const searchResults = document.getElementById('search_results');
            
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                
                clearTimeout(searchTimeout);
                
                if (searchTerm.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    fetch(`patient_list.php?search_patients=1&search_term=${encodeURIComponent(searchTerm)}`)
                        .then(response => response.json())
                        .then(patients => {
                            displaySearchResults(patients);
                        })
                        .catch(error => {
                            console.error('Search error:', error);
                        });
                }, 300);
            });
            
            function displaySearchResults(patients) {
                if (patients.length === 0) {
                    searchResults.innerHTML = '<div class="search-item">No patients found</div>';
                } else {
                    searchResults.innerHTML = patients.map(patient => 
                        `<div class="search-item" onclick="selectPatient('${patient.calling_name}')">
                            <strong>${patient.calling_name}</strong> (${patient.full_name})<br>
                            <small>NIC: ${patient.nic} | PHN: ${patient.hospital_number || 'Not assigned'} | Clinic: ${patient.clinic_number || 'Not assigned'}</small>
                        </div>`
                    ).join('');
                }
                searchResults.style.display = 'block';
            }
            
            // Hide search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
        });
        
        function selectPatient(callingName) {
            document.getElementById('patient_search').value = callingName;
            document.getElementById('search_results').style.display = 'none';
            
            // Auto-submit the search form
            document.querySelector('form').submit();
        }
        
        // PET Form Functions
        function openPETForm(patientId, patientName) {
            document.getElementById('pet_patient_id').value = patientId;
            document.getElementById('pet_patient_name').textContent = patientName;
            document.getElementById('petModal').style.display = 'block';
            document.getElementById('petForm').reset();
            document.getElementById('pet_patient_id').value = patientId;
            // Hide search, show selected patient
            document.getElementById('petPatientSearchSection').style.display = 'none';
            document.getElementById('petSelectedPatient').style.display = 'block';
        }
        
        function openPETFormWithSearch() {
            document.getElementById('petModal').style.display = 'block';
            document.getElementById('petForm').reset();
            // Show search, hide selected patient
            document.getElementById('petPatientSearchSection').style.display = 'block';
            document.getElementById('petSelectedPatient').style.display = 'none';
            // Clear and focus search field
            document.getElementById('petPatientSearch').value = '';
            document.getElementById('petSearchResults').style.display = 'none';
            setTimeout(() => {
                document.getElementById('petPatientSearch').focus();
            }, 100);
        }
        
        // Patient search for PET form
        let petSearchTimeout;
        document.addEventListener('DOMContentLoaded', function() {
            const petSearchInput = document.getElementById('petPatientSearch');
            const petSearchResults = document.getElementById('petSearchResults');
            
            if (petSearchInput) {
                petSearchInput.addEventListener('input', function() {
                    clearTimeout(petSearchTimeout);
                    const searchTerm = this.value.trim();
                    
                    if (searchTerm.length < 3) {
                        petSearchResults.style.display = 'none';
                        return;
                    }
                    
                    petSearchTimeout = setTimeout(function() {
                        fetch('patient_list.php?search_patients=1&search_term=' + encodeURIComponent(searchTerm))
                            .then(response => response.json())
                            .then(data => {
                                if (data.length > 0) {
                                    let html = '<div style="padding: 0.5rem;">';
                                    data.forEach(patient => {
                                        const hospitalNum = patient.hospital_number || '-';
                                        const clinicNum = patient.clinic_number || '-';
                                        html += `<div onclick="selectPETPatient(${patient.patient_id}, '${patient.calling_name.replace(/'/g, "\\'")}', '${patient.full_name.replace(/'/g, "\\'")}', '${patient.nic.replace(/'/g, "\\'")}', '${hospitalNum.replace(/'/g, "\\'")}')" 
                                                style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s;"
                                                onmouseover="this.style.background='#f0f0f0'" 
                                                onmouseout="this.style.background='white'">
                                            <div style="font-weight: 600; color: #2c3e50; font-size: 1rem;">${patient.full_name} (${patient.calling_name})</div>
                                            <div style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;">NIC: ${patient.nic} | PHN: ${hospitalNum} | Clinic: ${clinicNum}</div>
                                        </div>`;
                                    });
                                    html += '</div>';
                                    petSearchResults.innerHTML = html;
                                    petSearchResults.style.display = 'block';
                                } else {
                                    petSearchResults.innerHTML = '<div style="padding: 1rem; text-align: center; color: #666;">No patients found</div>';
                                    petSearchResults.style.display = 'block';
                                }
                            })
                            .catch(error => {
                                console.error('Search error:', error);
                            });
                    }, 300);
                });
                
                // Close search results when clicking outside
                document.addEventListener('click', function(e) {
                    if (!petSearchInput.contains(e.target) && !petSearchResults.contains(e.target)) {
                        petSearchResults.style.display = 'none';
                    }
                });
            }
        });
        
        function selectPETPatient(patientId, callingName, fullName, nic, hospitalNo) {
            // Set patient ID
            document.getElementById('pet_patient_id').value = patientId;
            // Update display
            document.getElementById('pet_patient_name').textContent = `${callingName} (${fullName}) - NIC: ${nic} - Hospital #: ${hospitalNo}`;
            // Hide search, show selected patient
            document.getElementById('petPatientSearchSection').style.display = 'none';
            document.getElementById('petSelectedPatient').style.display = 'block';
            // Clear search
            document.getElementById('petSearchResults').style.display = 'none';
            
            // Fetch and display last PET status
            fetchLastPETStatus(patientId);
        }
        
        function fetchLastPETStatus(patientId) {
            fetch(`patient_list.php?get_last_pet=1&patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    const statusDiv = document.getElementById('petCurrentStatus');
                    const contentDiv = document.getElementById('petCurrentStatusContent');
                    
                    if (data.has_record) {
                        const levelColors = {
                            'High': 'background: #ffebee; color: #c62828; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600;',
                            'High Average': 'background: #fff3e0; color: #e65100; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600;',
                            'Low Average': 'background: #e3f2fd; color: #1565c0; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600;',
                            'Low': 'background: #e8f5e9; color: #2e7d32; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600;'
                        };
                        const style = levelColors[data.pet_level] || 'background: #f5f5f5; color: #666; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600;';
                        
                        const testDate = new Date(data.test_date);
                        const formattedDate = testDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                        
                        contentDiv.innerHTML = `
                            <strong>Last PET Level:</strong> <span style="${style}">${data.pet_level}</span><br>
                            <strong>Test Date:</strong> ${formattedDate}
                        `;
                        statusDiv.style.display = 'block';
                    } else {
                        contentDiv.innerHTML = '<em>No previous PET test records found for this patient.</em>';
                        statusDiv.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching PET status:', error);
                });
        }
        
        function changePETPatient() {
            // Clear patient selection
            document.getElementById('pet_patient_id').value = '';
            document.getElementById('pet_patient_name').textContent = '';
            // Hide patient and status
            document.getElementById('petSelectedPatient').style.display = 'none';
            document.getElementById('petCurrentStatus').style.display = 'none';
            // Show search
            document.getElementById('petPatientSearchSection').style.display = 'block';
            // Focus on search field
            document.getElementById('petPatientSearch').value = '';
            document.getElementById('petPatientSearch').focus();
        }
        
        function validatePETForm() {
            const patientId = document.getElementById('pet_patient_id').value;
            if (!patientId) {
                alert('⚠️ Please select a patient before submitting the form.');
                return false;
            }
            return true;
        }
        
        function closePETModal() {
            document.getElementById('petModal').style.display = 'none';
        }
        
        function closePETHistoryModal() {
            document.getElementById('petHistoryModal').style.display = 'none';
        }
        
        // CAPD Form Functions
        function validateCAPDForm() {
            const patientId = document.getElementById('capd_patient_id').value;
            const catheterDate = document.getElementById('catheter_insertion_date').value;
            const capdStartDate = document.getElementById('capd_start_date').value;
            const surgeonId = document.getElementById('surgeon_doctor_id').value;
            
            if (!patientId) {
                alert('⚠️ Please select a patient before submitting the form.');
                return false;
            }
            
            if (!catheterDate) {
                alert('⚠️ Please enter the Catheter Insertion Date.');
                return false;
            }
            
            if (!surgeonId) {
                alert('⚠️ Please select a Surgeon Doctor.\n\nSurgeon Doctor is required when entering Catheter Insertion Date.');
                return false;
            }
            
            // If CAPD Start Date is provided, validate it
            if (capdStartDate) {
                const cathDate = new Date(catheterDate);
                const capdDate = new Date(capdStartDate);
                
                if (capdDate < cathDate) {
                    alert('⚠️ CAPD Start Date must be on or after the Catheter Insertion Date.');
                    return false;
                }
            }
            
            return true;
        }
        
        function openCAPDFormWithSearch() {
            document.getElementById('capdModal').style.display = 'block';
            document.getElementById('capdForm').reset();
            document.getElementById('capd_action').value = 'add_capd';
            document.getElementById('capd_id').value = '';
            // Show search, hide selected patient
            document.getElementById('capdPatientSearchSection').style.display = 'block';
            document.getElementById('capdSelectedPatient').style.display = 'none';
            document.getElementById('capdPatientInfo').style.display = 'none';
            // Clear and focus search field
            document.getElementById('capdPatientSearch').value = '';
            document.getElementById('capdSearchResults').style.display = 'none';
            setTimeout(() => {
                document.getElementById('capdPatientSearch').focus();
            }, 100);
        }
        
        function changeCApdPatient() {
            document.getElementById('capdPatientSearchSection').style.display = 'block';
            document.getElementById('capdSelectedPatient').style.display = 'none';
            document.getElementById('capdPatientInfo').style.display = 'none';
            document.getElementById('capdPatientSearch').value = '';
            document.getElementById('capdSearchResults').style.display = 'none';
            setTimeout(() => {
                document.getElementById('capdPatientSearch').focus();
            }, 100);
        }
        
        function closeCApdModal() {
            document.getElementById('capdModal').style.display = 'none';
        }
        
        function autoCalculateCApdStartDate() {
            const catheterDate = document.getElementById('catheter_insertion_date').value;
            if (!catheterDate) {
                alert('⚠️ Please enter the Catheter Insertion Date first.');
                return;
            }
            
            // Add 14 days to catheter insertion date
            const date = new Date(catheterDate);
            date.setDate(date.getDate() + 14);
            
            // Format date as YYYY-MM-DD
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const formattedDate = `${year}-${month}-${day}`;
            
            document.getElementById('capd_start_date').value = formattedDate;
            alert(`✅ CAPD Start Date set to ${new Date(formattedDate).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })} (14 days after insertion)`);
        }
        
        // Patient search for CAPD form
        let capdSearchTimeout;
        document.addEventListener('DOMContentLoaded', function() {
            const capdSearchInput = document.getElementById('capdPatientSearch');
            const capdSearchResults = document.getElementById('capdSearchResults');
            
            if (capdSearchInput) {
                capdSearchInput.addEventListener('input', function() {
                    clearTimeout(capdSearchTimeout);
                    const searchTerm = this.value.trim();
                    
                    if (searchTerm.length < 3) {
                        capdSearchResults.style.display = 'none';
                        return;
                    }
                    
                    capdSearchTimeout = setTimeout(function() {
                        fetch('patient_list.php?search_patients=1&search_term=' + encodeURIComponent(searchTerm))
                            .then(response => response.json())
                            .then(data => {
                                if (data.length > 0) {
                                    let html = '<div style="padding: 0.5rem;">';
                                    data.forEach(patient => {
                                        const hospitalNum = patient.hospital_number || '-';
                                        const clinicNum = patient.clinic_number || '-';
                                        html += `<div onclick="selectCApdPatient(${patient.patient_id}, '${patient.calling_name.replace(/'/g, "\\'")}', '${patient.full_name.replace(/'/g, "\\'")}', '${patient.nic.replace(/'/g, "\\'")}', '${hospitalNum.replace(/'/g, "\\'")}')" 
                                                style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s;"
                                                onmouseover="this.style.background='#f0f0f0'" 
                                                onmouseout="this.style.background='white'">
                                            <div style="font-weight: 600; color: #2c3e50; font-size: 1rem;">${patient.full_name} (${patient.calling_name})</div>
                                            <div style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;">NIC: ${patient.nic} | PHN: ${hospitalNum} | Clinic: ${clinicNum}</div>
                                        </div>`;
                                    });
                                    html += '</div>';
                                    capdSearchResults.innerHTML = html;
                                    capdSearchResults.style.display = 'block';
                                } else {
                                    capdSearchResults.innerHTML = '<div style="padding: 1rem; text-align: center; color: #666;">No patients found</div>';
                                    capdSearchResults.style.display = 'block';
                                }
                            })
                            .catch(error => {
                                console.error('Search error:', error);
                            });
                    }, 300);
                });
                
                // Close search results when clicking outside
                document.addEventListener('click', function(e) {
                    if (!capdSearchInput.contains(e.target) && !capdSearchResults.contains(e.target)) {
                        capdSearchResults.style.display = 'none';
                    }
                });
            }
        });
        
        function selectCApdPatient(patientId, callingName, fullName, nic, hospitalNo) {
            // Fetch patient details including age and nursing officer
            fetch('patient_list.php?get_patient_details=1&patient_id=' + patientId)
                .then(response => response.json())
                .then(data => {
                    // Set patient ID
                    document.getElementById('capd_patient_id').value = patientId;
                    // Update display
                    document.getElementById('capd_patient_name').textContent = `${callingName} (${fullName}) - NIC: ${nic} - PHN: ${hospitalNo}`;
                    
                    // Display patient info
                    document.getElementById('capd_display_calling_name').textContent = callingName;
                    document.getElementById('capd_display_full_name').textContent = fullName;
                    document.getElementById('capd_display_age').textContent = data.age || 'N/A';
                    document.getElementById('capd_display_phn').textContent = hospitalNo || '-';
                    document.getElementById('capd_display_clinic').textContent = data.clinic_number || '-';
                    document.getElementById('capd_display_nursing').textContent = data.nursing_name || '-';
                    
                    // Hide search, show selected patient
                    document.getElementById('capdPatientSearchSection').style.display = 'none';
                    document.getElementById('capdSelectedPatient').style.display = 'block';
                    document.getElementById('capdPatientInfo').style.display = 'block';
                    // Clear search
                    document.getElementById('capdSearchResults').style.display = 'none';
                    
                    // Check if patient has existing CAPD data
                    fetchCApdData(patientId);
                })
                .catch(error => {
                    console.error('Error fetching patient details:', error);
                    alert('Error fetching patient details. Please try again.');
                });
        }
        
        function fetchCApdData(patientId) {
            fetch(`patient_list.php?get_capd_data=1&patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.has_record) {
                        // Fill in existing data
                        document.getElementById('capd_id').value = data.capd_id;
                        document.getElementById('catheter_insertion_date').value = data.catheter_insertion_date || '';
                        document.getElementById('capd_start_date').value = data.capd_start_date || '';
                        document.getElementById('surgeon_doctor_id').value = data.surgeon_doctor_id || '';
                        // document.getElementById('nursing_officer_id').value = data.nursing_officer_id || ''; // Disabled until DB column exists
                        document.getElementById('capd_notes').value = data.notes || '';
                        document.getElementById('capd_action').value = 'edit_capd';
                        
                        // Show a hint that this is editing
                        const submitBtn = document.querySelector('#capdForm button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.textContent = '✏️ Update CAPD Record';
                        }
                    } else {
                        // Clear form for new entry
                        document.getElementById('capd_id').value = '';
                        document.getElementById('capd_action').value = 'add_capd';
                        document.getElementById('catheter_insertion_date').value = '';
                        document.getElementById('capd_start_date').value = '';
                        document.getElementById('surgeon_doctor_id').value = '';
                        document.getElementById('capd_notes').value = '';
                        
                        const submitBtn = document.querySelector('#capdForm button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.textContent = '💾 Save CAPD Record';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching CAPD data:', error);
                });
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const petModal = document.getElementById('petModal');
            const petHistoryModal = document.getElementById('petHistoryModal');
            const capdModal = document.getElementById('capdModal');
            
            if (event.target == petModal) {
                closePETModal();
            }
            if (event.target == petHistoryModal) {
                closePETHistoryModal();
            }
            if (event.target == capdModal) {
                closeCApdModal();
            }
        }
        
        <?php if (isset($_GET['view_pet'])): ?>
        // Auto-open PET history modal if view_pet parameter is set
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('petHistoryModal').style.display = 'block';
        });
        <?php endif; ?>
        
        <?php if (isset($_GET['view_all_pet'])): ?>
        // Auto-open all PET records modal if view_all_pet parameter is set
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('allPETModal').style.display = 'block';
        });
        <?php endif; ?>
    </script>

    <!-- All PET Records Modal -->
    <div id="allPETModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 2% auto; padding: 0; border-radius: 12px; width: 95%; max-width: 1400px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); max-height: 90vh; overflow: auto;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10;">
                <h2 style="margin: 0; font-size: 1.5rem;">🧪 All PET Test Records</h2>
                <button onclick="window.location.href='patient_list.php'" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0; width: 30px; height: 30px;">&times;</button>
            </div>
            
            <div style="padding: 1.5rem;">
                <?php if (!empty($all_pet_records)): ?>
                    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0 0 0.5rem 0; color: #2c3e50;">PET Test Summary</h3>
                            <p style="margin: 0;"><strong>Total Records:</strong> <?php echo count($all_pet_records); ?> tests</p>
                        </div>
                        <a href="patient_list.php" class="btn btn-secondary">← Back to Patients</a>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden;">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #34495e, #2c3e50); color: white;">
                                    <th style="padding: 1rem; text-align: left;">Patient</th>
                                    <th style="padding: 1rem; text-align: left;">NIC</th>
                                    <th style="padding: 1rem; text-align: left;">Hospital #</th>
                                    <th style="padding: 1rem; text-align: left;">Test Date</th>
                                    <th style="padding: 1rem; text-align: left;">PET Level</th>
                                    <th style="padding: 1rem; text-align: center;">D/P Creatinine</th>
                                    <th style="padding: 1rem; text-align: center;">D/D0 Glucose</th>
                                    <th style="padding: 1rem; text-align: center;">UF (mL)</th>
                                    <th style="padding: 1rem; text-align: left;">Notes</th>
                                    <th style="padding: 1rem; text-align: left;">Recorded By</th>
                                    <th style="padding: 1rem; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_pet_records as $record): ?>
                                    <tr style="border-bottom: 1px solid #e9ecef;">
                                        <td style="padding: 1rem;">
                                            <div style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($record['calling_name']); ?></div>
                                            <div style="font-size: 0.85rem; color: #7f8c8d;"><?php echo htmlspecialchars($record['full_name']); ?></div>
                                        </td>
                                        <td style="padding: 1rem; font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($record['nic']); ?></td>
                                        <td style="padding: 1rem;">
                                            <?php echo $record['hospital_number'] ? '<span style="background: #e8f5e8; color: #2d6a2d; padding: 0.3rem 0.6rem; border-radius: 4px; font-weight: 500; font-size: 0.85rem;">' . htmlspecialchars($record['hospital_number']) . '</span>' : '<span style="color: #999;">-</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem; font-weight: 600;"><?php echo date('M j, Y', strtotime($record['test_date'])); ?></td>
                                        <td style="padding: 1rem;">
                                            <?php 
                                            $level_colors = [
                                                'High' => 'background: #ffebee; color: #c62828;',
                                                'High Average' => 'background: #fff3e0; color: #e65100;',
                                                'Low Average' => 'background: #e3f2fd; color: #1565c0;',
                                                'Low' => 'background: #e8f5e9; color: #2e7d32;'
                                            ];
                                            $style = $level_colors[$record['pet_level']] ?? 'background: #f5f5f5; color: #666;';
                                            ?>
                                            <span style="<?php echo $style; ?> padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem; white-space: nowrap;">
                                                <?php echo htmlspecialchars($record['pet_level']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; text-align: center; font-family: monospace; font-weight: 600;">
                                            <?php echo $record['d_p_creatinine'] ? number_format($record['d_p_creatinine'], 2) : '<span style="color: #999;">-</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem; text-align: center; font-family: monospace; font-weight: 600;">
                                            <?php echo $record['d_d0_glucose'] ? number_format($record['d_d0_glucose'], 2) : '<span style="color: #999;">-</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem; text-align: center; font-weight: 600;">
                                            <?php echo $record['ultrafiltration'] ? $record['ultrafiltration'] : '<span style="color: #999;">-</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem; max-width: 200px; font-size: 0.9rem;">
                                            <?php echo $record['notes'] ? htmlspecialchars(substr($record['notes'], 0, 50)) . (strlen($record['notes']) > 50 ? '...' : '') : '<span style="color: #999; font-style: italic;">No notes</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span style="background: #e8f5e9; color: #2e7d32; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                                <?php echo htmlspecialchars($record['created_by_name']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; text-align: center;">
                                            <a href="?view_pet=<?php echo $record['patient_id']; ?>" class="btn btn-info btn-sm" title="View Patient PET History" style="font-size: 0.8rem; padding: 0.4rem 0.6rem;">
                                                📊 History
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #6c757d;">
                        <h3>🧪 No PET Records Found</h3>
                        <p>No Peritoneal Equilibration Test records have been added yet.</p>
                        <p style="margin-top: 1rem;">Start by adding PET tests from the patient list.</p>
                        <a href="patient_list.php" class="btn btn-primary" style="margin-top: 1rem;">← Back to Patients</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PET Form Modal -->
    <div id="petModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 1.5rem;">🧪 Peritoneal Equilibration Test (PET)</h2>
                <button onclick="closePETModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0; width: 30px; height: 30px;">&times;</button>
            </div>
            
            <?php if ($pet_message): ?>
                <div style="margin: 1rem 1.5rem; padding: 1rem; border-radius: 8px; <?php echo $pet_message_type == 'success' ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
                    <?php echo htmlspecialchars($pet_message); ?>
                </div>
            <?php endif; ?>
            
            <form id="petForm" method="POST" style="padding: 1.5rem;" onsubmit="return validatePETForm()">
                <input type="hidden" name="action" value="add_pet">
                <input type="hidden" id="pet_patient_id" name="patient_id">
                
                <!-- Patient Search Section (shown when opened from navbar) -->
                <div id="petPatientSearchSection" style="margin-bottom: 1rem; display: none;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">🔍 Search Patient *</label>
                    <div style="position: relative;">
                        <input type="text" id="petPatientSearch" placeholder="Type patient name or NIC to search..." 
                               style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        <div id="petSearchResults" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; max-height: 300px; overflow-y: auto; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1001;"></div>
                    </div>
                </div>
                
                <!-- Selected Patient Display -->
                <div id="petSelectedPatient" style="margin-bottom: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>Patient:</strong> <span id="pet_patient_name" style="color: #667eea; font-size: 1.1rem;"></span>
                        </div>
                        <button type="button" onclick="changePETPatient()" style="padding: 0.5rem 1rem; background: #667eea; color: white; border: none; border-radius: 6px; font-size: 0.9rem; cursor: pointer; font-weight: 600;">🔄 Change Patient</button>
                    </div>
                </div>
                
                <!-- Current PET Status -->
                <div id="petCurrentStatus" style="margin-bottom: 1rem; padding: 1rem; background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 8px; display: none;">
                    <div style="font-weight: 600; color: #e65100; margin-bottom: 0.5rem;">📊 Current PET Status</div>
                    <div id="petCurrentStatusContent" style="color: #666;"></div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">Test Date *</label>
                    <input type="date" name="test_date" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">PET Level *</label>
                    <select name="pet_level" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        <option value="">Select PET Level</option>
                        <option value="High">High Transporter</option>
                        <option value="High Average">High Average Transporter</option>
                        <option value="Low Average">Low Average Transporter</option>
                        <option value="Low">Low Transporter</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">D/P Creatinine Ratio</label>
                    <input type="number" step="0.01" name="d_p_creatinine" placeholder="e.g., 0.81" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                    <small style="color: #666; display: block; margin-top: 0.25rem;">Dialysate to Plasma ratio at 4 hours</small>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">D/D0 Glucose Ratio</label>
                    <input type="number" step="0.01" name="d_d0_glucose" placeholder="e.g., 0.38" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                    <small style="color: #666; display: block; margin-top: 0.25rem;">Dialysate glucose at 4h/0h ratio</small>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">Ultrafiltration (mL)</label>
                    <input type="number" name="ultrafiltration" placeholder="e.g., 400" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                    <small style="color: #666; display: block; margin-top: 0.25rem;">Net ultrafiltration volume</small>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">Notes</label>
                    <textarea name="notes" rows="3" placeholder="Additional notes or observations..." style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; resize: vertical;"></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" style="flex: 1; padding: 0.75rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">💾 Save PET Record</button>
                    <button type="button" onclick="closePETModal()" style="padding: 0.75rem 1.5rem; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PET History Modal -->
    <div id="petHistoryModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 3% auto; padding: 0; border-radius: 12px; width: 95%; max-width: 1200px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); max-height: 90vh; overflow: auto;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10;">
                <h2 style="margin: 0; font-size: 1.5rem;">📊 PET Test History</h2>
                <button onclick="closePETHistoryModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0; width: 30px; height: 30px;">&times;</button>
            </div>
            
            <div style="padding: 1.5rem;">
                <?php if (!empty($pet_records)): ?>
                    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                        <h3 style="margin: 0 0 0.5rem 0; color: #2c3e50;">Patient Information</h3>
                        <p style="margin: 0;"><strong>Name:</strong> <?php echo htmlspecialchars($pet_records[0]['calling_name']); ?> (<?php echo htmlspecialchars($pet_records[0]['full_name']); ?>)</p>
                        <p style="margin: 0.25rem 0 0 0;"><strong>NIC:</strong> <?php echo htmlspecialchars($pet_records[0]['nic']); ?></p>
                        <p style="margin: 0.25rem 0 0 0;"><strong>Total Tests:</strong> <?php echo count($pet_records); ?></p>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden;">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #34495e, #2c3e50); color: white;">
                                    <th style="padding: 1rem; text-align: left;">Test Date</th>
                                    <th style="padding: 1rem; text-align: left;">PET Level</th>
                                    <th style="padding: 1rem; text-align: center;">D/P Creatinine</th>
                                    <th style="padding: 1rem; text-align: center;">D/D0 Glucose</th>
                                    <th style="padding: 1rem; text-align: center;">Ultrafiltration</th>
                                    <th style="padding: 1rem; text-align: left;">Notes</th>
                                    <th style="padding: 1rem; text-align: left;">Recorded By</th>
                                    <th style="padding: 1rem; text-align: left;">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pet_records as $record): ?>
                                    <tr style="border-bottom: 1px solid #e9ecef;">
                                        <td style="padding: 1rem; font-weight: 600;"><?php echo date('M j, Y', strtotime($record['test_date'])); ?></td>
                                        <td style="padding: 1rem;">
                                            <?php 
                                            $level_colors = [
                                                'High' => 'background: #ffebee; color: #c62828;',
                                                'High Average' => 'background: #fff3e0; color: #e65100;',
                                                'Low Average' => 'background: #e3f2fd; color: #1565c0;',
                                                'Low' => 'background: #e8f5e9; color: #2e7d32;'
                                            ];
                                            $style = $level_colors[$record['pet_level']] ?? 'background: #f5f5f5; color: #666;';
                                            ?>
                                            <span style="<?php echo $style; ?> padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; white-space: nowrap;">
                                                <?php echo htmlspecialchars($record['pet_level']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; text-align: center; font-family: monospace; font-weight: 600;">
                                            <?php echo $record['d_p_creatinine'] ? number_format($record['d_p_creatinine'], 2) : '<span style="color: #999;">-</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem; text-align: center; font-family: monospace; font-weight: 600;">
                                            <?php echo $record['d_d0_glucose'] ? number_format($record['d_d0_glucose'], 2) : '<span style="color: #999;">-</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem; text-align: center; font-weight: 600;">
                                            <?php echo $record['ultrafiltration'] ? $record['ultrafiltration'] . ' mL' : '<span style="color: #999;">-</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem; max-width: 250px;">
                                            <?php echo $record['notes'] ? htmlspecialchars($record['notes']) : '<span style="color: #999; font-style: italic;">No notes</span>'; ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span style="background: #e8f5e9; color: #2e7d32; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                                <?php echo htmlspecialchars($record['created_by_name']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; font-size: 0.9rem; color: #666;">
                                            <?php echo date('M j, Y g:i A', strtotime($record['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #6c757d;">
                        <h3>📊 No PET Records Found</h3>
                        <p>No Peritoneal Equilibration Test records have been added for this patient yet.</p>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem; text-align: center;">
                    <button onclick="closePETHistoryModal()" style="padding: 0.75rem 2rem; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CAPD Status Modal -->
    <div id="capdModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 1.5rem;">💊 CAPD Status Entry</h2>
                <button onclick="closeCApdModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0; width: 30px; height: 30px;">&times;</button>
            </div>
            
            <?php if ($capd_message): ?>
                <div style="margin: 1rem 1.5rem; padding: 1rem; border-radius: 8px; <?php echo $capd_message_type == 'success' ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
                    <?php echo htmlspecialchars($capd_message); ?>
                </div>
            <?php endif; ?>
            
            <form id="capdForm" method="POST" style="padding: 1.5rem;" onsubmit="return validateCAPDForm()">
                <input type="hidden" name="action" id="capd_action" value="add_capd">
                <input type="hidden" id="capd_id" name="capd_id">
                <input type="hidden" id="capd_patient_id" name="patient_id">
                
                <!-- Patient Search Section -->
                <div id="capdPatientSearchSection" style="margin-bottom: 1rem; display: none;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">🔍 Search Patient *</label>
                    <div style="position: relative;">
                        <input type="text" id="capdPatientSearch" placeholder="Type patient name or NIC to search..." 
                               style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        <div id="capdSearchResults" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; max-height: 300px; overflow-y: auto; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1001;"></div>
                    </div>
                </div>
                
                <!-- Selected Patient Info -->
                <div id="capdSelectedPatient" style="margin-bottom: 1.5rem; display: none;">
                    <div style="background: #e8f5e9; padding: 1rem; border-radius: 8px; border-left: 4px solid #4caf50;">
                        <div style="font-size: 0.85rem; color: #2d6a2d; font-weight: 600; margin-bottom: 0.25rem;">Selected Patient:</div>
                        <div id="capd_patient_name" style="font-size: 1.1rem; font-weight: 600; color: #2c3e50; margin-bottom: 0.5rem;"></div>
                        <button type="button" onclick="changeCApdPatient()" style="background: #ff9800; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">Change Patient</button>
                    </div>
                </div>
                
                <!-- Patient Info Display -->
                <div id="capdPatientInfo" style="margin-bottom: 1.5rem; display: none; background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <div style="font-size: 0.85rem; color: #666; font-weight: 600;">Calling Name</div>
                            <div id="capd_display_calling_name" style="font-weight: 600; color: #2c3e50;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; font-weight: 600;">Full Name</div>
                            <div id="capd_display_full_name" style="font-weight: 600; color: #2c3e50;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; font-weight: 600;">Age</div>
                            <div id="capd_display_age" style="font-weight: 600; color: #2c3e50;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; font-weight: 600;">PHN</div>
                            <div id="capd_display_phn" style="font-weight: 600; color: #2c3e50;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; font-weight: 600;">Clinic Number</div>
                            <div id="capd_display_clinic" style="font-weight: 600; color: #2c3e50;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #666; font-weight: 600;">Nursing Officer</div>
                            <div id="capd_display_nursing" style="font-weight: 600; color: #2c3e50;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Catheter Insertion Date -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">Catheter Insertion Date *</label>
                    <input type="date" id="catheter_insertion_date" name="catheter_insertion_date" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                </div>
                
                <!-- Surgeon Doctor Dropdown -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">Surgeon Doctor *</label>
                    <select id="surgeon_doctor_id" name="surgeon_doctor_id" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        <option value="">-- Select Surgeon Doctor --</option>
                        <?php
                        $surgeon_query = "SELECT doctor_id, doctor_name, specialization FROM doctors WHERE specialization = 'Surgeon' ORDER BY doctor_name";
                        $surgeon_result = $conn->query($surgeon_query);
                        if ($surgeon_result && $surgeon_result->num_rows > 0) {
                            while ($surgeon = $surgeon_result->fetch_assoc()) {
                                echo '<option value="' . $surgeon['doctor_id'] . '">' . htmlspecialchars($surgeon['doctor_name']) . ' (' . htmlspecialchars($surgeon['specialization']) . ')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Nursing Officer Dropdown -->
                <!-- TODO: Uncomment when nursing_officer_id column is added to capd_status table
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">Assigned Nursing Officer</label>
                    <select id="nursing_officer_id" name="nursing_officer_id" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        <option value="">-- Select Nursing Officer (Optional) --</option>
                        <?php
                        $nursing_query = "SELECT nursing_id, nursing_name, grade FROM nursing_officers ORDER BY nursing_name";
                        $nursing_result = $conn->query($nursing_query);
                        if ($nursing_result && $nursing_result->num_rows > 0) {
                            while ($nursing = $nursing_result->fetch_assoc()) {
                                echo '<option value="' . $nursing['nursing_id'] . '">' . htmlspecialchars($nursing['nursing_name']) . ' (' . htmlspecialchars($nursing['grade']) . ')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                -->
                
                <!-- CAPD Start Date -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">CAPD Start Date (Optional)</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="date" id="capd_start_date" name="capd_start_date" style="flex: 1; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem;">
                        <button type="button" onclick="autoCalculateCApdStartDate()" style="padding: 0.75rem 1rem; background: #17a2b8; color: white; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; white-space: nowrap;">Auto (+14d)</button>
                    </div>
                    <small style="color: #666; display: block; margin-top: 0.25rem;">Typically starts 14 days after catheter insertion. Click "Auto (+14d)" to calculate.</small>
                </div>
                
                <!-- Notes -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">Notes</label>
                    <textarea id="capd_notes" name="notes" rows="3" placeholder="Additional notes or observations..." style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; resize: vertical;"></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" style="flex: 1; padding: 0.75rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">💾 Save CAPD Record</button>
                    <button type="button" onclick="closeCApdModal()" style="padding: 0.75rem 1.5rem; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
