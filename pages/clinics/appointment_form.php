<?php
// CAPD Clinic module removed
include_once '../../includes/header.php';
echo '<div class="container"><div class="card"><h3>CAPD Clinic Module Removed</h3><p>This module has been removed from the application.</p><a href="../../index.php" class="btn btn-secondary">← Back to Dashboard</a></div></div>';
include_once '../../includes/footer.php';
exit();
<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$appointment_id = $clinic_id = $patient_id = $appointment_date = $appointment_time = $appointment_type = $chief_complaint = $notes = '';
$error = '';
$success = '';

// Pre-select clinic if provided in URL
if (isset($_GET['clinic_id']) && !empty($_GET['clinic_id'])) {
    $clinic_id = $conn->real_escape_string($_GET['clinic_id']);
}

// Check if editing
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM clinic_appointments WHERE appointment_id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $appointment = $result->fetch_assoc();
        $appointment_id = $appointment['appointment_id'];
        $clinic_id = $appointment['clinic_id'];
        $patient_id = $appointment['patient_id'];
        $appointment_date = $appointment['appointment_date'];
        $appointment_time = $appointment['appointment_time'];
        $appointment_type = $appointment['appointment_type'];
        $chief_complaint = $appointment['chief_complaint'];
        $notes = $appointment['notes'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $clinic_id = $conn->real_escape_string($_POST['clinic_id']);
    $patient_id = $conn->real_escape_string($_POST['patient_id']);
    $appointment_date = $conn->real_escape_string($_POST['appointment_date']);
    $appointment_time = $conn->real_escape_string($_POST['appointment_time']);
    $appointment_type = $conn->real_escape_string($_POST['appointment_type']);
    $chief_complaint = trim($conn->real_escape_string($_POST['chief_complaint']));
    $notes = trim($conn->real_escape_string($_POST['notes']));
    
    // Validation
    if (empty($clinic_id)) {
        $error = "Please select a clinic!";
    } elseif (empty($patient_id)) {
        $error = "Please select a patient!";
    } elseif (empty($appointment_date)) {
        $error = "Appointment date is required!";
    } elseif (empty($appointment_time)) {
        $error = "Appointment time is required!";
    } elseif ($appointment_date < date('Y-m-d')) {
        $error = "Appointment date cannot be in the past!";
    } else {
        // Check for conflicting appointments (same patient, same date/time)
        $conflict_sql = "SELECT appointment_id FROM clinic_appointments 
                        WHERE patient_id = $patient_id 
                        AND appointment_date = '$appointment_date' 
                        AND appointment_time = '$appointment_time' 
                        AND appointment_status NOT IN ('Cancelled', 'Completed')";
        
        if (!empty($appointment_id)) {
            $conflict_sql .= " AND appointment_id != $appointment_id";
        }
        
        $conflict_result = $conn->query($conflict_sql);
        if ($conflict_result->num_rows > 0) {
            $error = "This patient already has an appointment at the same date and time!";
        } else {
            // Check clinic capacity for the day
            $capacity_sql = "SELECT c.max_appointments_per_day,
                            COUNT(ca.appointment_id) as booked_appointments
                            FROM clinics c
                            LEFT JOIN clinic_appointments ca ON c.clinic_id = ca.clinic_id 
                                AND ca.appointment_date = '$appointment_date'
                                AND ca.appointment_status NOT IN ('Cancelled')
                            WHERE c.clinic_id = $clinic_id
                            GROUP BY c.clinic_id";
            
            $capacity_result = $conn->query($capacity_sql);
            $capacity_data = $capacity_result->fetch_assoc();
            
            if ($capacity_data['booked_appointments'] >= $capacity_data['max_appointments_per_day'] && empty($appointment_id)) {
                $error = "This clinic has reached its maximum capacity for the selected date!";
            } else {
                if (!empty($appointment_id)) {
                    // Update existing appointment
                    $sql = "UPDATE clinic_appointments SET 
                            clinic_id = $clinic_id,
                            patient_id = $patient_id,
                            appointment_date = '$appointment_date',
                            appointment_time = '$appointment_time',
                            appointment_type = '$appointment_type',
                            chief_complaint = '$chief_complaint',
                            notes = '$notes'
                            WHERE appointment_id = $appointment_id";
                } else {
                    // Create new appointment
                    $created_by = $_SESSION['user_id'];
                    $sql = "INSERT INTO clinic_appointments (clinic_id, patient_id, appointment_date, appointment_time, appointment_type, chief_complaint, notes, created_by) 
                            VALUES ($clinic_id, $patient_id, '$appointment_date', '$appointment_time', '$appointment_type', '$chief_complaint', '$notes', $created_by)";
                }
                
                if ($conn->query($sql) === TRUE) {
                    $success = !empty($appointment_id) ? "Appointment updated successfully!" : "Appointment scheduled successfully!";
                    
                    // Reset form for new appointment
                    if (empty($appointment_id)) {
                        $patient_id = $appointment_date = $appointment_time = $appointment_type = $chief_complaint = $notes = '';
                    }
                } else {
                    $error = "Error saving appointment: " . $conn->error;
                }
            }
        }
    }
}

// Get clinics for dropdown
$clinics_result = $conn->query("SELECT clinic_id, clinic_name, clinic_code FROM clinics WHERE is_active = 1 ORDER BY clinic_name ASC");

// Get patients for dropdown
$patients_result = $conn->query("SELECT patient_id, calling_name, full_name, nic FROM patients ORDER BY calling_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($appointment_id) ? 'Edit' : 'Schedule'; ?> CAPD Appointment</title>
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
        
        .form-group.full-width {
            grid-column: 1 / -1;
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
            min-height: 100px;
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
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .info-box {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            border-left: 4px solid #17a2b8;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
        }
        
        .info-box h4 {
            color: #0c5460;
            margin: 0 0 0.5rem 0;
        }
        
        .info-box p {
            color: #0c5460;
            margin: 0;
            font-size: 0.9rem;
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
            <a href="clinic_list.php">Clinics</a>
            <a href="appointment_list.php">Appointments</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo !empty($appointment_id) ? '✏️ Edit CAPD Appointment' : '📅 Schedule CAPD Appointment'; ?></h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <h4>📋 Appointment Guidelines</h4>
                <p>Please ensure the patient information is correct and the selected time slot is available. Appointments cannot be scheduled for past dates.</p>
            </div>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="clinic_id">Clinic <span class="required">*</span></label>
                        <select id="clinic_id" name="clinic_id" required>
                            <option value="">Select Clinic</option>
                            <?php 
                            $clinics_result->data_seek(0); // Reset pointer
                            while ($clinic = $clinics_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $clinic['clinic_id']; ?>" <?php echo ($clinic_id == $clinic['clinic_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($clinic['clinic_name']); ?> (<?php echo htmlspecialchars($clinic['clinic_code']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="patient_id">Patient <span class="required">*</span></label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php while ($patient = $patients_result->fetch_assoc()): ?>
                                <option value="<?php echo $patient['patient_id']; ?>" <?php echo ($patient_id == $patient['patient_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['calling_name']); ?> (<?php echo htmlspecialchars($patient['nic']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="appointment_date">Appointment Date <span class="required">*</span></label>
                        <input type="date" id="appointment_date" name="appointment_date" value="<?php echo htmlspecialchars($appointment_date); ?>" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="appointment_time">Appointment Time <span class="required">*</span></label>
                        <input type="time" id="appointment_time" name="appointment_time" value="<?php echo htmlspecialchars($appointment_time); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="appointment_type">Appointment Type</label>
                    <select id="appointment_type" name="appointment_type">
                        <option value="CAPD Training" <?php echo ($appointment_type == 'CAPD Training') ? 'selected' : ''; ?>>CAPD Training Session</option>
                        <option value="Routine CAPD Follow-up" <?php echo ($appointment_type == 'Routine CAPD Follow-up') ? 'selected' : ''; ?>>Routine CAPD Follow-up</option>
                        <option value="CAPD Adequacy Assessment" <?php echo ($appointment_type == 'CAPD Adequacy Assessment') ? 'selected' : ''; ?>>CAPD Adequacy Assessment</option>
                        <option value="Exit Site Check" <?php echo ($appointment_type == 'Exit Site Check') ? 'selected' : ''; ?>>Exit Site Examination</option>
                        <option value="CAPD Complication" <?php echo ($appointment_type == 'CAPD Complication') ? 'selected' : ''; ?>>CAPD Complication Management</option>
                        <option value="Catheter Assessment" <?php echo ($appointment_type == 'Catheter Assessment') ? 'selected' : ''; ?>>Catheter Function Assessment</option>
                        <option value="Emergency CAPD Care" <?php echo ($appointment_type == 'Emergency CAPD Care') ? 'selected' : ''; ?>>Emergency CAPD Care</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="chief_complaint">Chief Complaint</label>
                    <textarea id="chief_complaint" name="chief_complaint" placeholder="Brief description of the patient's main concern or symptoms..."><?php echo htmlspecialchars($chief_complaint); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea id="notes" name="notes" placeholder="Any additional information or special instructions..."><?php echo htmlspecialchars($notes); ?></textarea>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo !empty($appointment_id) ? '💾 Update Appointment' : '📅 Schedule Appointment'; ?>
                    </button>
                    <a href="appointment_list.php" class="btn btn-secondary">← Back to List</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>