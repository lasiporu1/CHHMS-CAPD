<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$patient_id = $death_date = $death_notes = '';
$error = $success = '';
$patient_info = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $conn->real_escape_string($_POST['patient_id']);
    $death_date = $conn->real_escape_string($_POST['death_date']);
    $death_notes = $conn->real_escape_string($_POST['death_notes']);
    
    // Validation
    if (empty($patient_id)) {
        $error = "Please select a patient!";
    } elseif (empty($death_date)) {
        $error = "Death date is required!";
    } else {
        // Check if patient exists and is not already deceased
        $check_sql = "SELECT patient_id, calling_name, full_name, nic, patient_status FROM patients WHERE patient_id = $patient_id";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows == 0) {
            $error = "Patient not found!";
        } else {
            $patient = $check_result->fetch_assoc();
            if ($patient['patient_status'] == 'Deceased') {
                $error = "This patient is already marked as deceased!";
            } else {
                // Add patient_status column if it doesn't exist
                $check_status = "SHOW COLUMNS FROM patients LIKE 'patient_status'";
                $status_exists = $conn->query($check_status);
                if ($status_exists->num_rows == 0) {
                    $alter_status = "ALTER TABLE patients ADD COLUMN patient_status ENUM('Active', 'Deceased', 'Inactive') DEFAULT 'Active'";
                    $conn->query($alter_status);
                }
                
                // Add death_date and death_notes columns if they don't exist
                $check_death = "SHOW COLUMNS FROM patients LIKE 'death_date'";
                $death_exists = $conn->query($check_death);
                if ($death_exists->num_rows == 0) {
                    $alter_death = "ALTER TABLE patients ADD COLUMN death_date DATE NULL, ADD COLUMN death_notes TEXT NULL";
                    $conn->query($alter_death);
                }
                
                // Update patient status to deceased
                $update_sql = "UPDATE patients SET 
                              patient_status = 'Deceased',
                              death_date = '$death_date',
                              death_notes = '$death_notes'
                              WHERE patient_id = $patient_id";
                
                if ($conn->query($update_sql) === TRUE) {
                    // Automatically close all active medicines for this patient
                    $close_medicines_sql = "UPDATE medicines SET 
                                           end_date = '$death_date',
                                           status = 'Discontinued'
                                           WHERE patient_id = $patient_id 
                                           AND (status = 'Active' OR end_date IS NULL OR end_date > '$death_date')";
                    $conn->query($close_medicines_sql);
                    
                    $success = "Patient " . htmlspecialchars($patient['calling_name']) . " has been marked as deceased successfully! All active medications have been automatically closed.";
                    // Clear form
                    $patient_id = $death_date = $death_notes = '';
                    $patient_info = null;
                } else {
                    $error = "Error updating patient status: " . $conn->error;
                }
            }
        }
    }
}

// Handle patient search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $search_sql = "SELECT patient_id, calling_name, full_name, nic, clinic_number, patient_status 
                   FROM patients 
                   WHERE (calling_name LIKE '%$search%' OR full_name LIKE '%$search%' OR nic LIKE '%$search%' OR clinic_number LIKE '%$search%')
                   AND (patient_status IS NULL OR patient_status != 'Deceased')
                   LIMIT 10";
    $search_result = $conn->query($search_sql);
}

// Get patient info if ID is provided
if (isset($_GET['patient_id']) && !empty($_GET['patient_id'])) {
    $pid = $conn->real_escape_string($_GET['patient_id']);
    $patient_sql = "SELECT patient_id, calling_name, full_name, nic, clinic_number, contact_number, date_of_birth, patient_status 
                    FROM patients 
                    WHERE patient_id = $pid";
    $patient_result = $conn->query($patient_sql);
    if ($patient_result->num_rows > 0) {
        $patient_info = $patient_result->fetch_assoc();
        $patient_id = $patient_info['patient_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Death - Hospital Management System</title>
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
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .card-header h2 {
            font-size: 2rem;
            margin: 0 0 1rem 0;
        }
        
        .card-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .card-body {
            padding: 2.5rem;
        }
        
        .search-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .search-box label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .patient-results {
            margin-top: 1rem;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .patient-item {
            background: white;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .patient-item:hover {
            border-color: #2c3e50;
            transform: translateX(5px);
        }
        
        .patient-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #3498db;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .info-value {
            color: #34495e;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
            background: white;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
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
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(44, 62, 80, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-left: 4px solid #f39c12;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 8px;
        }
        
        .warning-box h4 {
            color: #856404;
            margin: 0 0 0.5rem 0;
        }
        
        .warning-box p {
            color: #856404;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Hospital Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="admission_list.php">Admissions</a>
            <a href="../patients/patient_list.php">Patients</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>⚰️ Register Patient Death</h2>
                <p>Record patient death without ward admission</p>
            </div>
            
            <div class="card-body">
                <!-- Warning -->
                <div class="warning-box">
                    <h4>⚠️ Important Notice</h4>
                    <p>This form is for registering patient deaths that occurred outside of ward admission. Once registered, the patient status will be permanently changed to "Deceased". Please ensure all information is accurate before proceeding.</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Patient Search -->
                <div class="search-box">
                    <label for="patient_search">Search Patient</label>
                    <input type="text" id="patient_search" placeholder="Search by name, NIC, or clinic number..." autocomplete="off">
                    
                    <?php if (isset($search_result) && $search_result->num_rows > 0): ?>
                        <div class="patient-results">
                            <?php while ($row = $search_result->fetch_assoc()): ?>
                                <div class="patient-item" onclick="selectPatient(<?php echo $row['patient_id']; ?>)">
                                    <strong><?php echo htmlspecialchars($row['calling_name']); ?></strong>
                                    (<?php echo htmlspecialchars($row['full_name']); ?>)<br>
                                    <small>NIC: <?php echo htmlspecialchars($row['nic']); ?> | Clinic: <?php echo htmlspecialchars($row['clinic_number']); ?></small>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Patient Information -->
                <?php if ($patient_info): ?>
                    <div class="patient-info">
                        <h3 style="margin-bottom: 1rem; color: #2c3e50;">👤 Selected Patient</h3>
                        <div class="info-row">
                            <span class="info-label">Patient Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient_info['calling_name']) . ' (' . htmlspecialchars($patient_info['full_name']) . ')'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">NIC:</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient_info['nic']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Clinic Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient_info['clinic_number']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date of Birth:</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($patient_info['date_of_birth'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Contact:</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient_info['contact_number']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Death Information Form -->
                    <form method="POST">
                        <input type="hidden" name="patient_id" value="<?php echo $patient_info['patient_id']; ?>">
                        
                        <div class="form-group">
                            <label for="death_date">Death Date *</label>
                            <input type="date" id="death_date" name="death_date" value="<?php echo $death_date; ?>" required max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="death_notes">Death Notes / Cause of Death</label>
                            <textarea id="death_notes" name="death_notes" placeholder="Enter cause of death, circumstances, or other relevant information..."><?php echo htmlspecialchars($death_notes); ?></textarea>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to register this patient as deceased? This action cannot be undone.')">
                                ⚰️ Register Death
                            </button>
                            <a href="../patients/patient_list.php" class="btn btn-secondary">
                                ← Back to Patients
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 2rem;">
                        Please search and select a patient to register their death.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Patient search with debounce
        let searchTimeout;
        document.getElementById('patient_search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const search = this.value.trim();
            
            if (search.length >= 2) {
                searchTimeout = setTimeout(() => {
                    window.location.href = '?search=' + encodeURIComponent(search);
                }, 500);
            }
        });
        
        function selectPatient(patientId) {
            window.location.href = '?patient_id=' + patientId;
        }
        
        // Set max date to today for death date
        document.addEventListener('DOMContentLoaded', function() {
            const deathDateInput = document.getElementById('death_date');
            if (deathDateInput && !deathDateInput.value) {
                deathDateInput.value = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>
