<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get admission ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admission_list.php");
    exit();
}

$admission_id = $conn->real_escape_string($_GET['id']);

// Fetch admission details
$sql = "SELECT wa.*, 
               p.calling_name, p.full_name, p.nic,
               ar.reason_name,
               d.doctor_name, no.nursing_name
        FROM ward_admissions wa
        LEFT JOIN patients p ON wa.patient_id = p.patient_id
        LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
        LEFT JOIN doctors d ON wa.attending_doctor_id = d.doctor_id
        LEFT JOIN nursing_officers no ON wa.nursing_officer_id = no.nursing_id
        WHERE wa.admission_id = $admission_id AND wa.admission_status = 'Active'";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: admission_list.php");
    exit();
}

$admission = $result->fetch_assoc();

$discharge_date = $discharge_time = $discharge_notes = $discharge_status = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $discharge_date = $conn->real_escape_string($_POST['discharge_date']);
    $discharge_time = $conn->real_escape_string($_POST['discharge_time']);
    $discharge_notes = $conn->real_escape_string($_POST['discharge_notes']);
    $discharge_status = $conn->real_escape_string($_POST['discharge_status']);
    
    // Validation
    if (empty($discharge_date)) {
        $error = "Discharge date is required!";
    } elseif (empty($discharge_time)) {
        $error = "Discharge time is required!";
    } elseif (empty($discharge_status)) {
        $error = "Discharge status is required!";
    } elseif (strtotime($discharge_date . ' ' . $discharge_time) < strtotime($admission['admission_date'] . ' ' . $admission['admission_time'])) {
        $error = "Discharge date/time cannot be earlier than admission date/time (" . date('M j, Y g:i A', strtotime($admission['admission_date'] . ' ' . $admission['admission_time'])) . ")!";
    } else {
        // Add discharge_status column if it doesn't exist
        $check_column = "SHOW COLUMNS FROM ward_admissions LIKE 'discharge_status'";
        $column_exists = $conn->query($check_column);
        if ($column_exists->num_rows == 0) {
            $alter_sql = "ALTER TABLE ward_admissions ADD COLUMN discharge_status ENUM('Complete', 'Pending', 'Death') DEFAULT 'Complete'";
            $conn->query($alter_sql);
        } else {
            // Update enum to include Death if not already there
            $row = $column_exists->fetch_assoc();
            if (strpos($row['Type'], 'Death') === false) {
                $alter_enum = "ALTER TABLE ward_admissions MODIFY COLUMN discharge_status ENUM('Complete', 'Pending', 'Death') DEFAULT 'Complete'";
                $conn->query($alter_enum);
            }
        }
        
        // Add patient_status column to patients table if it doesn't exist
        $check_patient_status = "SHOW COLUMNS FROM patients LIKE 'patient_status'";
        $patient_status_exists = $conn->query($check_patient_status);
        if ($patient_status_exists->num_rows == 0) {
            $alter_patient = "ALTER TABLE patients ADD COLUMN patient_status ENUM('Active', 'Deceased', 'Inactive') DEFAULT 'Active'";
            $conn->query($alter_patient);
        }
        
        // Add death_date and death_notes columns if they don't exist
        $check_death_date = "SHOW COLUMNS FROM patients LIKE 'death_date'";
        $death_date_exists = $conn->query($check_death_date);
        if ($death_date_exists->num_rows == 0) {
            $alter_death = "ALTER TABLE patients ADD COLUMN death_date DATE NULL, ADD COLUMN death_notes TEXT NULL";
            $conn->query($alter_death);
        }
        
        // Update admission record
        $sql = "UPDATE ward_admissions SET 
                admission_status = 'Discharged',
                discharge_date = '$discharge_date',
                discharge_time = '$discharge_time',
                discharge_notes = '$discharge_notes',
                discharge_status = '$discharge_status',
                updated_at = CURRENT_TIMESTAMP
                WHERE admission_id = $admission_id";
        
        if ($conn->query($sql) === TRUE) {
            // If discharge status is Death, update patient master file
            if ($discharge_status == 'Death') {
                $patient_id = $admission['patient_id'];
                $update_patient = "UPDATE patients SET 
                                   patient_status = 'Deceased',
                                   death_date = '$discharge_date',
                                   death_notes = '$discharge_notes'
                                   WHERE patient_id = $patient_id";
                $conn->query($update_patient);
            }
            
            header("Location: admission_view.php?id=$admission_id");
            exit();
        } else {
            $error = "Error discharging patient: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discharge Patient - <?php echo htmlspecialchars($admission['calling_name']); ?></title>
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
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
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
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
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
        <h1>🏥 Ward Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="admission_list.php">Admissions</a>
            <a href="admission_view.php?id=<?php echo $admission_id; ?>">View Admission</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>📋 Discharge Patient</h2>
                <p>Complete the discharge process for this patient</p>
            </div>
            
            <div class="card-body">
                <!-- Patient Information -->
                <div class="patient-info">
                    <h3 style="margin-bottom: 1rem; color: #2c3e50;">👤 Patient Information</h3>
                    <div class="info-row">
                        <span class="info-label">Patient Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($admission['calling_name']) . ' (' . htmlspecialchars($admission['full_name']) . ')'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">NIC:</span>
                        <span class="info-value"><?php echo htmlspecialchars($admission['nic']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Admission Reason:</span>
                        <span class="info-value"><?php echo htmlspecialchars($admission['reason_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Admission Date/Time:</span>
                        <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($admission['admission_date'] . ' ' . $admission['admission_time'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ward Bed:</span>
                        <span class="info-value"><?php echo $admission['ward_bed'] ? htmlspecialchars($admission['ward_bed']) : 'Not assigned'; ?></span>
                    </div>
                </div>
                
                <!-- Warning -->
                <div class="warning-box">
                    <h4>⚠️ Important Notice</h4>
                    <p>Once you discharge this patient, their admission status will be permanently changed to "Discharged". Please ensure all necessary procedures have been completed before proceeding.</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="section-title">📋 Discharge Information</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="discharge_date">Discharge Date *</label>
                            <input type="date" id="discharge_date" name="discharge_date" value="<?php echo $discharge_date; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="discharge_time">Discharge Time *</label>
                            <input type="time" id="discharge_time" name="discharge_time" value="<?php echo $discharge_time; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="discharge_status">Discharge Status *</label>
                        <select id="discharge_status" name="discharge_status" required>
                            <option value="">Select Discharge Status</option>
                            <option value="Complete" <?php echo ($discharge_status == 'Complete') ? 'selected' : ''; ?>>Complete - Patient fully recovered and ready for discharge</option>
                            <option value="Pending" <?php echo ($discharge_status == 'Pending') ? 'selected' : ''; ?>>Pending - Patient needs follow-up care or monitoring</option>
                            <option value="Death" <?php echo ($discharge_status == 'Death') ? 'selected' : ''; ?>>Death - Patient deceased</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="discharge_notes">Discharge Notes</label>
                        <textarea id="discharge_notes" name="discharge_notes" placeholder="Enter discharge summary, condition at discharge, follow-up instructions, medications prescribed, etc..."><?php echo htmlspecialchars($discharge_notes); ?></textarea>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to discharge this patient? This action cannot be undone.')">
                            📋 Discharge Patient
                        </button>
                        <a href="admission_view.php?id=<?php echo $admission_id; ?>" class="btn btn-secondary">
                            ← Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const admissionDate = '<?php echo $admission['admission_date']; ?>';
            const admissionTime = '<?php echo $admission['admission_time']; ?>';
            const dischargeDateInput = document.getElementById('discharge_date');
            const dischargeTimeInput = document.getElementById('discharge_time');
            
            // Set minimum date to admission date
            dischargeDateInput.min = admissionDate;
            
            // Set default discharge date and time to current
            if (!dischargeDateInput.value) {
                const today = new Date().toISOString().split('T')[0];
                dischargeDateInput.value = today >= admissionDate ? today : admissionDate;
            }
            if (!dischargeTimeInput.value) {
                const now = new Date();
                const timeString = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                dischargeTimeInput.value = timeString;
            }
            
            // Validate discharge date/time
            function validateDischargeDateTime() {
                const selectedDate = dischargeDateInput.value;
                const selectedTime = dischargeTimeInput.value;
                
                if (!selectedDate || !selectedTime) {
                    return true; // Let HTML5 validation handle required fields
                }
                
                // Create datetime objects for comparison
                const dischargeDateTime = new Date(selectedDate + 'T' + selectedTime);
                const admissionDateTime = new Date(admissionDate + 'T' + admissionTime);
                
                if (dischargeDateTime < admissionDateTime) {
                    const admissionFormatted = admissionDateTime.toLocaleDateString() + ' ' + 
                                             admissionDateTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    alert('Discharge date/time cannot be earlier than admission date/time (' + admissionFormatted + ')');
                    
                    // Set to admission datetime if invalid
                    dischargeDateInput.value = admissionDate;
                    dischargeTimeInput.value = admissionTime;
                    return false;
                }
                
                return true;
            }
            
            dischargeDateInput.addEventListener('change', validateDischargeDateTime);
            dischargeTimeInput.addEventListener('change', validateDischargeDateTime);
            
            // Validate on form submission
            document.querySelector('form').addEventListener('submit', function(e) {
                if (!validateDischargeDateTime()) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>