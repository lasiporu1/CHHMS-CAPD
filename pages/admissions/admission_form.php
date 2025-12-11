<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Create tables if they don't exist (same as admission_list.php)
$create_reasons_table_sql = "CREATE TABLE IF NOT EXISTS admission_reasons (
    reason_id INT AUTO_INCREMENT PRIMARY KEY,
    reason_name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_reasons_table_sql);

$create_table_sql = "CREATE TABLE IF NOT EXISTS ward_admissions (
    admission_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    admission_reason_id INT NOT NULL,
    admission_date DATE NOT NULL,
    admission_time TIME NOT NULL,
    attending_doctor_id INT,
    nursing_officer_id INT,
    ward_bed VARCHAR(50),
    admission_notes TEXT,
    admission_status ENUM('Active', 'Discharged', 'Transferred') DEFAULT 'Active',
    discharge_date DATE NULL,
    discharge_time TIME NULL,
    discharge_notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Create ward_beds table if it doesn't exist
$create_beds_table = "CREATE TABLE IF NOT EXISTS ward_beds (
    bed_id INT AUTO_INCREMENT PRIMARY KEY,
    ward_name VARCHAR(100) NOT NULL,
    bed_number VARCHAR(50) NOT NULL,
    bed_type ENUM('General', 'ICU', 'CCU', 'Private', 'Semi-Private', 'Emergency') DEFAULT 'General',
    bed_status ENUM('Available', 'Occupied', 'Maintenance', 'Reserved') DEFAULT 'Available',
    room_number VARCHAR(50),
    floor_number VARCHAR(10),
    equipment TEXT,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ward_bed (ward_name, bed_number)
)";
$conn->query($create_beds_table);

$admission_id = $patient_id = $admission_reason_id = $admission_date = $admission_time = '';
$attending_doctor_id = $nursing_officer_id = $ward_bed = $admission_notes = '';
$error = '';

// Check for duplicate admission error
if (isset($_GET['error']) && $_GET['error'] == 'duplicate') {
    $error = "This patient already has an active admission. You are now editing the existing admission record. Please update the details if needed or discharge/transfer the patient first before creating a new admission.";
}

// Check if editing
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM ward_admissions WHERE admission_id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $admission = $result->fetch_assoc();
        $admission_id = $admission['admission_id'];
        $patient_id = $admission['patient_id'];
        $admission_reason_id = $admission['admission_reason_id'];
        $admission_date = $admission['admission_date'];
        $admission_time = $admission['admission_time'];
        $attending_doctor_id = $admission['attending_doctor_id'];
        $nursing_officer_id = $admission['nursing_officer_id'];
        $ward_bed = $admission['ward_bed'];
        $admission_notes = $admission['admission_notes'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $conn->real_escape_string($_POST['patient_id']);
    $admission_reason_id = $conn->real_escape_string($_POST['admission_reason_id']);
    $admission_date = $conn->real_escape_string($_POST['admission_date']);
    $admission_time = $conn->real_escape_string($_POST['admission_time']);
    $attending_doctor_id = !empty($_POST['attending_doctor_id']) ? $conn->real_escape_string($_POST['attending_doctor_id']) : 'NULL';
    $nursing_officer_id = !empty($_POST['nursing_officer_id']) ? $conn->real_escape_string($_POST['nursing_officer_id']) : 'NULL';
    // Handle ward bed from either dropdown or manual input
    $ward_bed = '';
    if (!empty($_POST['ward_bed'])) {
        $ward_bed = $conn->real_escape_string($_POST['ward_bed']);
    } elseif (!empty($_POST['ward_bed_manual'])) {
        $ward_bed = $conn->real_escape_string($_POST['ward_bed_manual']);
    }
    $admission_notes = $conn->real_escape_string($_POST['admission_notes']);
    
    // Validation
    if (empty($patient_id)) {
        $error = "Please select a patient!";
    } elseif (empty($admission_reason_id)) {
        $error = "Please select an admission reason!";
    } elseif (empty($admission_date)) {
        $error = "Admission date is required!";
    } elseif (empty($admission_time)) {
        $error = "Admission time is required!";
    } else {
        // Check for existing active admission for this patient (only for new admissions)
        if (empty($admission_id)) {
            $check_sql = "SELECT admission_id FROM ward_admissions WHERE patient_id = $patient_id AND admission_status = 'Active'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result->num_rows > 0) {
                $existing_admission = $check_result->fetch_assoc();
                $existing_admission_id = $existing_admission['admission_id'];
                header("Location: admission_form.php?edit=$existing_admission_id&error=duplicate");
                exit();
            }
        }
        if (!empty($admission_id)) {
            // Update existing admission
            $sql = "UPDATE ward_admissions SET 
                    patient_id = $patient_id,
                    admission_reason_id = $admission_reason_id,
                    admission_date = '$admission_date',
                    admission_time = '$admission_time',
                    attending_doctor_id = $attending_doctor_id,
                    nursing_officer_id = $nursing_officer_id,
                    ward_bed = '$ward_bed',
                    admission_notes = '$admission_notes'
                    WHERE admission_id = $admission_id";
        } else {
            // Create new admission
            $created_by = $_SESSION['user_id'];
            $sql = "INSERT INTO ward_admissions 
                    (patient_id, admission_reason_id, admission_date, admission_time, 
                     attending_doctor_id, nursing_officer_id, ward_bed, admission_notes, created_by) 
                    VALUES ($patient_id, $admission_reason_id, '$admission_date', '$admission_time', 
                            $attending_doctor_id, $nursing_officer_id, '$ward_bed', '$admission_notes', $created_by)";
        }
        
        if ($conn->query($sql) === TRUE) {
            header("Location: admission_list.php");
            exit();
        } else {
            $error = "Error saving admission: " . $conn->error;
        }
    }
}

// Get patients for dropdown
$patients_sql = "SELECT patient_id, calling_name, full_name, nic, hospital_number, clinic_number FROM patients ORDER BY calling_name";
$patients_result = $conn->query($patients_sql);

// Get admission reasons for dropdown
$reasons_sql = "SELECT reason_id, reason_name FROM admission_reasons WHERE is_active = 1 ORDER BY reason_name";
$reasons_result = $conn->query($reasons_sql);

// Get doctors for dropdown
$doctors_sql = "SELECT doctor_id, doctor_name, specialization FROM doctors ORDER BY doctor_name";
$doctors_result = $conn->query($doctors_sql);

// Get nursing officers for dropdown
$nursing_sql = "SELECT nursing_id, nursing_name FROM nursing_officers ORDER BY nursing_name";
$nursing_result = $conn->query($nursing_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($admission_id) ? 'Edit' : 'New'; ?> Ward Admission</title>
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
            max-width: 1200px;
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
        .form-group select,
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
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .section-title {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            padding: 1rem 1.5rem;
            margin: 2rem 0 1.5rem 0;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .patient-search {
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
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .search-item:hover {
            background-color: #f8f9fa;
        }
        
        .selected-patient {
            background: #e8f5e8;
            padding: 0.75rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            display: none;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Ward Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="admission_list.php">Admissions</a>
            <a href="admission_reasons_list.php">Admission Reasons</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo !empty($admission_id) ? '✏️ Edit' : '🏨 New'; ?> Ward Admission</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="section-title">👤 Patient Information</div>
                
                <div class="form-group patient-search">
                    <label for="patient_search">Search Patient *</label>
                    <input type="text" id="patient_search" placeholder="Search by name, NIC, Hospital Number, Clinic Number..." autocomplete="off">
                    <input type="hidden" id="patient_id" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="search-results" id="search_results"></div>
                    <div class="selected-patient" id="selected_patient"></div>
                    <?php if (empty($admission_id)): ?>
                        <div style="background: #e8f4fd; border: 1px solid #bee5eb; border-radius: 4px; padding: 0.75rem; margin-top: 0.5rem; font-size: 0.875rem; color: #0c5460;">
                            <strong>ℹ️ Note:</strong> Patients with active admissions will be marked with a warning. You cannot create duplicate active admissions.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="section-title">🏥 Admission Details</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="admission_reason_id">Admission Reason *</label>
                        <select id="admission_reason_id" name="admission_reason_id" required>
                            <option value="">Select Admission Reason</option>
                            <?php while ($reason = $reasons_result->fetch_assoc()): ?>
                                <option value="<?php echo $reason['reason_id']; ?>" 
                                        <?php echo ($admission_reason_id == $reason['reason_id']) ? 'selected' : ''; ?>>
                                    <?php echo $reason['reason_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ward_bed">Ward Bed</label>
                        <select id="ward_bed" name="ward_bed">
                            <option value="">Select Available Bed</option>
                            <?php
                            // Get available beds from bed management system with error handling
                            try {
                                $beds_query = "SELECT bed_id, ward_name, bed_number, bed_type, room_number 
                                              FROM ward_beds 
                                              WHERE bed_status = 'Available' AND is_active = 1 
                                              ORDER BY ward_name, bed_number";
                                $beds_result = $conn->query($beds_query);
                                
                                if ($beds_result && $beds_result->num_rows > 0) {
                                    while ($bed = $beds_result->fetch_assoc()) {
                                        $bed_display = $bed['ward_name'] . ' - ' . $bed['bed_number'];
                                        if ($bed['room_number']) {
                                            $bed_display .= ' (Room ' . $bed['room_number'] . ')';
                                        }
                                        $bed_display .= ' - ' . $bed['bed_type'];
                                        
                                        $selected = ($ward_bed == $bed['ward_name'] . ' - ' . $bed['bed_number']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($bed['ward_name'] . ' - ' . $bed['bed_number']) . "' $selected>" . htmlspecialchars($bed_display) . "</option>";
                                    }
                                } else {
                                    echo "<option value='' disabled>No beds available - Please setup beds first</option>";
                                }
                            } catch (Exception $e) {
                                // If ward_beds table doesn't exist, show manual input option
                                echo "<option value='' disabled>Bed management not setup - Enter manually below</option>";
                            }
                            ?>
                        </select>
                        <input type="text" id="ward_bed_manual" name="ward_bed_manual" 
                               placeholder="Or enter bed manually (e.g., General Ward - A-01)" 
                               value="<?php echo htmlspecialchars($ward_bed); ?>"
                               style="margin-top: 0.5rem; display: block; width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="color: #666; display: block; margin-top: 0.5rem;">
                            💡 Select from dropdown or enter manually. 
                            <a href="bed_management.php" target="_blank" style="color: #4a90e2;">Manage beds</a>
                        </small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="admission_date">Admission Date *</label>
                        <input type="date" id="admission_date" name="admission_date" value="<?php echo $admission_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="admission_time">Admission Time *</label>
                        <input type="time" id="admission_time" name="admission_time" value="<?php echo $admission_time; ?>" required>
                    </div>
                </div>
                
                <div class="section-title">👨‍⚕️ Medical Staff Assignment</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="attending_doctor_id">Attending Doctor</label>
                        <select id="attending_doctor_id" name="attending_doctor_id">
                            <option value="">Select Doctor</option>
                            <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                                <option value="<?php echo $doctor['doctor_id']; ?>" 
                                        <?php echo ($attending_doctor_id == $doctor['doctor_id']) ? 'selected' : ''; ?>>
                                    <?php echo $doctor['doctor_name']; ?><?php echo !empty($doctor['specialization']) ? ' - ' . $doctor['specialization'] : ''; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nursing_officer_id">Nursing Officer</label>
                        <select id="nursing_officer_id" name="nursing_officer_id">
                            <option value="">Select Nursing Officer</option>
                            <?php while ($nurse = $nursing_result->fetch_assoc()): ?>
                                <option value="<?php echo $nurse['nursing_id']; ?>" 
                                        <?php echo ($nursing_officer_id == $nurse['nursing_id']) ? 'selected' : ''; ?>>
                                    <?php echo $nurse['nursing_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="admission_notes">Admission Notes</label>
                    <textarea id="admission_notes" name="admission_notes" placeholder="Enter any additional notes about the admission..."><?php echo htmlspecialchars($admission_notes); ?></textarea>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo !empty($admission_id) ? '💾 Update Admission' : '🏨 Admit Patient'; ?>
                    </button>
                    <a href="admission_list.php" class="btn btn-secondary">← Back to List</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('patient_search');
            const searchResults = document.getElementById('search_results');
            const patientIdInput = document.getElementById('patient_id');
            const selectedPatientDiv = document.getElementById('selected_patient');
            
            let searchTimeout;
            
            // Set default date and time
            if (!document.getElementById('admission_date').value) {
                document.getElementById('admission_date').value = new Date().toISOString().split('T')[0];
            }
            if (!document.getElementById('admission_time').value) {
                const now = new Date();
                const timeString = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                document.getElementById('admission_time').value = timeString;
            }
            
            // Load existing patient if editing
            <?php if (!empty($patient_id)): ?>
                fetch('../../pages/patients/patient_list.php?search_patients=1&search_term=<?php echo $patient_id; ?>')
                    .then(response => response.json())
                    .then(patients => {
                        if (patients.length > 0) {
                            const patient = patients[0];
                            displaySelectedPatient(patient);
                        }
                    });
            <?php endif; ?>
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                
                clearTimeout(searchTimeout);
                
                if (searchTerm.length < 2) {
                    searchResults.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    fetch(`../../pages/patients/patient_list.php?search_patients=1&search_term=${encodeURIComponent(searchTerm)}`)
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
                searchResults.innerHTML = '';
                
                if (patients.length === 0) {
                    searchResults.innerHTML = '<div class="search-item">No patients found</div>';
                } else {
                    patients.forEach(patient => {
                        const item = document.createElement('div');
                        item.className = 'search-item';
                        
                        let statusWarning = '';
                        if (patient.admission_id && patient.admission_status === 'Active') {
                            statusWarning = '<br><small style="color: #e74c3c; font-weight: bold;">⚠️ Already has active admission</small>';
                        }
                        
                        item.innerHTML = `
                            <strong>${patient.calling_name}</strong> (${patient.full_name})<br>
                            <small>NIC: ${patient.nic} | Hospital: ${patient.hospital_number || 'N/A'} | Clinic: ${patient.clinic_number || 'N/A'}</small>
                            ${statusWarning}
                        `;
                        item.addEventListener('click', () => selectPatient(patient));
                        searchResults.appendChild(item);
                    });
                }
                
                searchResults.style.display = 'block';
            }
            
            function selectPatient(patient) {
                // Check if patient has active admission
                if (patient.admission_id && patient.admission_status === 'Active') {
                    const confirmMessage = `⚠️ WARNING: This patient (${patient.calling_name}) already has an active admission.\n\nDo you want to:\n- Click OK to edit the existing admission\n- Click Cancel to select a different patient`;
                    
                    if (confirm(confirmMessage)) {
                        // Redirect to edit existing admission
                        window.location.href = `admission_form.php?edit=${patient.admission_id}&error=duplicate`;
                        return;
                    } else {
                        // Clear selection and return
                        searchInput.value = '';
                        searchResults.style.display = 'none';
                        return;
                    }
                }
                
                patientIdInput.value = patient.patient_id;
                searchInput.value = patient.calling_name;
                searchResults.style.display = 'none';
                displaySelectedPatient(patient);
            }
            
            function displaySelectedPatient(patient) {
                selectedPatientDiv.innerHTML = `
                    <strong>Selected Patient:</strong> ${patient.calling_name} (${patient.full_name})<br>
                    <small>NIC: ${patient.nic} | Hospital: ${patient.hospital_number || 'N/A'} | Clinic: ${patient.clinic_number || 'N/A'}</small>
                `;
                selectedPatientDiv.style.display = 'block';
            }
            
            // Hide search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>