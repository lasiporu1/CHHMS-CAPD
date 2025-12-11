<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$clinic_id = $clinic_name = $clinic_code = $department = $location = $phone = $email = $operating_hours = $consultant_doctor_id = $max_appointments_per_day = $appointment_duration = '';
$error = '';

// Check if editing
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM clinics WHERE clinic_id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $clinic = $result->fetch_assoc();
        $clinic_id = $clinic['clinic_id'];
        $clinic_name = $clinic['clinic_name'];
        $clinic_code = $clinic['clinic_code'];
        $department = $clinic['department'];
        $location = $clinic['location'];
        $phone = $clinic['phone'];
        $email = $clinic['email'];
        $operating_hours = $clinic['operating_hours'];
        $consultant_doctor_id = $clinic['consultant_doctor_id'];
        $max_appointments_per_day = $clinic['max_appointments_per_day'];
        $appointment_duration = $clinic['appointment_duration'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $clinic_name = trim($conn->real_escape_string($_POST['clinic_name']));
    $clinic_code = trim($conn->real_escape_string($_POST['clinic_code']));
    $department = trim($conn->real_escape_string($_POST['department']));
    $location = trim($conn->real_escape_string($_POST['location']));
    $phone = trim($conn->real_escape_string($_POST['phone']));
    $email = trim($conn->real_escape_string($_POST['email']));
    $operating_hours = trim($conn->real_escape_string($_POST['operating_hours']));
    $consultant_doctor_id = !empty($_POST['consultant_doctor_id']) ? $conn->real_escape_string($_POST['consultant_doctor_id']) : 'NULL';
    $max_appointments_per_day = $conn->real_escape_string($_POST['max_appointments_per_day']);
    $appointment_duration = $conn->real_escape_string($_POST['appointment_duration']);
    
    // Validation
    if (empty($clinic_name)) {
        $error = "Clinic name is required!";
    } elseif (empty($clinic_code)) {
        $error = "Clinic code is required!";
    } elseif (empty($max_appointments_per_day) || $max_appointments_per_day <= 0) {
        $error = "Valid maximum appointments per day is required!";
    } elseif (empty($appointment_duration) || $appointment_duration <= 0) {
        $error = "Valid appointment duration is required!";
    } else {
        // Check for duplicate clinic code (excluding current record if editing)
        $check_sql = "SELECT clinic_id FROM clinics WHERE clinic_code = '$clinic_code'";
        if (!empty($clinic_id)) {
            $check_sql .= " AND clinic_id != $clinic_id";
        }
        
        $check_result = $conn->query($check_sql);
        if ($check_result->num_rows > 0) {
            $error = "This clinic code already exists!";
        } else {
            if (!empty($clinic_id)) {
                // Update existing clinic
                $sql = "UPDATE clinics SET 
                        clinic_name = '$clinic_name',
                        clinic_code = '$clinic_code',
                        department = '$department',
                        location = '$location',
                        phone = '$phone',
                        email = '$email',
                        operating_hours = '$operating_hours',
                        consultant_doctor_id = $consultant_doctor_id,
                        max_appointments_per_day = $max_appointments_per_day,
                        appointment_duration = $appointment_duration
                        WHERE clinic_id = $clinic_id";
            } else {
                // Create new clinic
                $sql = "INSERT INTO clinics (clinic_name, clinic_code, department, location, phone, email, operating_hours, consultant_doctor_id, max_appointments_per_day, appointment_duration) 
                        VALUES ('$clinic_name', '$clinic_code', '$department', '$location', '$phone', '$email', '$operating_hours', $consultant_doctor_id, $max_appointments_per_day, $appointment_duration)";
            }
            
            if ($conn->query($sql) === TRUE) {
                header("Location: clinic_list.php");
                exit();
            } else {
                $error = "Error saving clinic: " . $conn->error;
            }
        }
    }
}

// Get doctors for dropdown
$doctors_result = $conn->query("SELECT doctor_id, doctor_name, specialization FROM doctors ORDER BY doctor_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($clinic_id) ? 'Edit' : 'Add'; ?> Clinic</title>
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
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .required {
            color: #e74c3c;
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
            <h2><?php echo !empty($clinic_id) ? '✏️ Edit Clinic' : '➕ Add New Clinic'; ?></h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="clinic_name">Clinic Name <span class="required">*</span></label>
                        <input type="text" id="clinic_name" name="clinic_name" value="<?php echo htmlspecialchars($clinic_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="clinic_code">Clinic Code <span class="required">*</span></label>
                        <input type="text" id="clinic_code" name="clinic_code" value="<?php echo htmlspecialchars($clinic_code); ?>" required placeholder="e.g., GM001">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($department); ?>" placeholder="e.g., Internal Medicine">
                    </div>
                    <div class="form-group">
                        <label for="consultant_doctor_id">Consultant Doctor</label>
                        <select id="consultant_doctor_id" name="consultant_doctor_id">
                            <option value="">Select Doctor</option>
                            <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                                <option value="<?php echo $doctor['doctor_id']; ?>" <?php echo ($consultant_doctor_id == $doctor['doctor_id']) ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?> 
                                    <?php if ($doctor['specialization']): ?>
                                        - <?php echo htmlspecialchars($doctor['specialization']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>" placeholder="e.g., Ground Floor - Wing A">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="011-1234567">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="clinic@hospital.com">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="operating_hours">Operating Hours</label>
                    <textarea id="operating_hours" name="operating_hours" placeholder="e.g., Mon-Fri: 8:00 AM - 4:00 PM"><?php echo htmlspecialchars($operating_hours); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="max_appointments_per_day">Max Appointments per Day <span class="required">*</span></label>
                        <input type="number" id="max_appointments_per_day" name="max_appointments_per_day" value="<?php echo htmlspecialchars($max_appointments_per_day); ?>" required min="1" placeholder="50">
                    </div>
                    <div class="form-group">
                        <label for="appointment_duration">Appointment Duration (minutes) <span class="required">*</span></label>
                        <input type="number" id="appointment_duration" name="appointment_duration" value="<?php echo htmlspecialchars($appointment_duration); ?>" required min="5" max="120" placeholder="15">
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo !empty($clinic_id) ? '💾 Update Clinic' : '➕ Add Clinic'; ?>
                    </button>
                    <a href="clinic_list.php" class="btn btn-secondary">← Back to List</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>