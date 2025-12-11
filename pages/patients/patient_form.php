<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$patient_id = $calling_name = $full_name = $nic = $hospital_number = $clinic_number = '';
$date_of_birth = $sex = $blood_group = $contact_number = $address = '';
$guardian_name = $guardian_contact_number = $assigned_nursing_officer = '';
$error = '';

if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM patients WHERE patient_id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
        $patient_id = $patient['patient_id'];
        $calling_name = $patient['calling_name'];
        $full_name = $patient['full_name'];
        $nic = $patient['nic'];
        $hospital_number = $patient['hospital_number'];
        $clinic_number = $patient['clinic_number'];
        $date_of_birth = $patient['date_of_birth'];
        $sex = $patient['sex'];
        $blood_group = $patient['blood_group'];
        $contact_number = $patient['contact_number'];
        $address = $patient['address'];
        $guardian_name = $patient['guardian_name'];
        $guardian_contact_number = $patient['guardian_contact_number'];
        $assigned_nursing_officer = $patient['assigned_nursing_officer'] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate required fields
    if (empty($_POST['calling_name'])) {
        $error = "Calling name is required!";
    } elseif (empty($_POST['full_name'])) {
        $error = "Full name is required!";
    } elseif (empty($_POST['nic'])) {
        $error = "National Identity Card (NIC) is required!";
    } elseif (empty($_POST['date_of_birth'])) {
        $error = "Date of birth is required!";
    } elseif (empty($_POST['sex'])) {
        $error = "Gender selection is required!";
    } elseif (empty($_POST['contact_number'])) {
        $error = "Contact number is required!";
    } elseif (empty($_POST['address'])) {
        $error = "Address is required!";
    } elseif (!preg_match('/^([0-9]{9}[vVxX]|[0-9]{12})$/i', $_POST['nic'])) {
        $error = "Please enter a valid NIC format (9 digits + V/X for old format OR 12 digits for new format)!";
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $_POST['contact_number'])) {
        $error = "Please enter a valid contact number (numbers, spaces, +, -, () only)!";
    } else {
        $calling_name = $conn->real_escape_string($_POST['calling_name']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $nic = strtoupper($conn->real_escape_string($_POST['nic']));
        $hospital_number = $conn->real_escape_string($_POST['hospital_number']);
        $clinic_number = $conn->real_escape_string($_POST['clinic_number']);
        $date_of_birth = $conn->real_escape_string($_POST['date_of_birth']);
        $sex = $conn->real_escape_string($_POST['sex']);
        $blood_group = $conn->real_escape_string($_POST['blood_group']);
        $contact_number = $conn->real_escape_string($_POST['contact_number']);
        $address = $conn->real_escape_string($_POST['address']);
        $guardian_name = $conn->real_escape_string($_POST['guardian_name']);
        $guardian_contact_number = $conn->real_escape_string($_POST['guardian_contact_number']);
        $assigned_nursing_officer = !empty($_POST['assigned_nursing_officer']) ? $conn->real_escape_string($_POST['assigned_nursing_officer']) : 'NULL';

        // Add nursing officer column if it doesn't exist
        $check_column = "SHOW COLUMNS FROM patients LIKE 'assigned_nursing_officer'";
        $column_exists = $conn->query($check_column);
        if ($column_exists->num_rows == 0) {
            $alter_sql = "ALTER TABLE patients ADD COLUMN assigned_nursing_officer INT NULL";
            $conn->query($alter_sql);
        }

        if (isset($_GET['edit'])) {
            // Check for duplicate NIC in other records when updating
            $patient_id = $conn->real_escape_string($_GET['edit']);
            $check_nic_sql = "SELECT patient_id FROM patients WHERE nic = '$nic' AND patient_id != $patient_id";
            $nic_result = $conn->query($check_nic_sql);
            
            if ($nic_result->num_rows > 0) {
                $error = "Another patient with this NIC ($nic) already exists in the system!";
            } else {
                // Update patient
                $sql = "UPDATE patients SET calling_name='$calling_name', full_name='$full_name', nic='$nic', hospital_number='$hospital_number', clinic_number='$clinic_number', date_of_birth='$date_of_birth', sex='$sex', blood_group='$blood_group', contact_number='$contact_number', address='$address', guardian_name='$guardian_name', guardian_contact_number='$guardian_contact_number', assigned_nursing_officer=$assigned_nursing_officer WHERE patient_id=$patient_id";
            
                if ($conn->query($sql) === TRUE) {
                    header("Location: patient_list.php");
                    exit();
                } else {
                    if (strpos($conn->error, 'Duplicate entry') !== false && strpos($conn->error, 'nic') !== false) {
                        $error = "This NIC number is already registered to another patient!";
                    } elseif (strpos($conn->error, 'Data too long') !== false) {
                        $error = "Some information is too long. Please check your entries and try again.";
                    } elseif (strpos($conn->error, 'Incorrect date') !== false) {
                        $error = "Please enter a valid date of birth.";
                    } else {
                        $error = "Unable to update patient information. Please check your data and try again.";
                    }
                }
            }
        } else {
            // Check for duplicate NIC, hospital number, and clinic number before creating new patient
            $check_duplicates_sql = "SELECT patient_id, 'nic' as field_type FROM patients WHERE nic = '$nic'
                                    UNION
                                    SELECT patient_id, 'hospital_number' as field_type FROM patients WHERE hospital_number = '$hospital_number' AND hospital_number != ''
                                    UNION 
                                    SELECT patient_id, 'clinic_number' as field_type FROM patients WHERE clinic_number = '$clinic_number' AND clinic_number != ''";
            $duplicate_result = $conn->query($check_duplicates_sql);
            
            if ($duplicate_result->num_rows > 0) {
                $duplicate = $duplicate_result->fetch_assoc();
                if ($duplicate['field_type'] == 'nic') {
                    $error = "A patient with this NIC ($nic) is already registered in the system!";
                } elseif ($duplicate['field_type'] == 'hospital_number') {
                    $error = "This hospital number ($hospital_number) is already assigned to another patient!";
                } elseif ($duplicate['field_type'] == 'clinic_number') {
                    $error = "This clinic number ($clinic_number) is already assigned to another patient!";
                }
            } else {
                // Create new patient
                $sql = "INSERT INTO patients (calling_name, full_name, nic, hospital_number, clinic_number, date_of_birth, sex, blood_group, contact_number, address, guardian_name, guardian_contact_number, assigned_nursing_officer) 
                        VALUES ('$calling_name', '$full_name', '$nic', '$hospital_number', '$clinic_number', '$date_of_birth', '$sex', '$blood_group', '$contact_number', '$address', '$guardian_name', '$guardian_contact_number', $assigned_nursing_officer)";
                
                if ($conn->query($sql) === TRUE) {
                    header("Location: patient_list.php");
                    exit();
                } else {
                    if (strpos($conn->error, 'Duplicate entry') !== false) {
                        if (strpos($conn->error, 'nic') !== false) {
                            $error = "This NIC number is already registered to another patient!";
                        } elseif (strpos($conn->error, 'hospital_number') !== false) {
                            $error = "This hospital number is already assigned to another patient!";
                        } elseif (strpos($conn->error, 'clinic_number') !== false) {
                            $error = "This clinic number is already assigned to another patient!";
                        } else {
                            $error = "This patient information already exists in the system!";
                        }
                    } elseif (strpos($conn->error, 'Data too long') !== false) {
                        $error = "Some information is too long. Please check your entries and try again.";
                    } elseif (strpos($conn->error, 'Incorrect date') !== false) {
                        $error = "Please enter a valid date of birth.";
                    } else {
                        $error = "Unable to save patient information. Please check your data and try again.";
                    }
                }
            }
        }
    }
}

// Get nursing officers for dropdown
$nursing_officers_sql = "SELECT nursing_id, nursing_name FROM nursing_officers ORDER BY nursing_name ASC";
$nursing_officers_result = $conn->query($nursing_officers_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($_GET['edit']) ? 'Edit Patient' : 'Add Patient'; ?></title>
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Patient Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="patient_list.php">Patients</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo isset($_GET['edit']) ? 'Edit Patient' : 'Add New Patient'; ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <?php if ($patient_id): ?>
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <?php endif; ?>
                
                <div class="section-title">Personal Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="calling_name">Calling Name *</label>
                        <input type="text" id="calling_name" name="calling_name" value="<?php echo $calling_name; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo $full_name; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nic">National Identity Card (NIC) *</label>
                        <input type="text" id="nic" name="nic" value="<?php echo $nic; ?>" required>
                        <small style="color: #666; display: block; margin-top: 0.25rem;">
                            📝 Old format: 9 digits + V/X (e.g., 123456789V) | New format: 12 digits (e.g., 199812345678)
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth *</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $date_of_birth; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sex">Sex *</label>
                        <select id="sex" name="sex" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo $sex == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $sex == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $sex == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="blood_group">Blood Group</label>
                        <select id="blood_group" name="blood_group">
                            <option value="">Select Blood Group</option>
                            <option value="O+" <?php echo $blood_group == 'O+' ? 'selected' : ''; ?>>O+</option>
                            <option value="O-" <?php echo $blood_group == 'O-' ? 'selected' : ''; ?>>O-</option>
                            <option value="A+" <?php echo $blood_group == 'A+' ? 'selected' : ''; ?>>A+</option>
                            <option value="A-" <?php echo $blood_group == 'A-' ? 'selected' : ''; ?>>A-</option>
                            <option value="B+" <?php echo $blood_group == 'B+' ? 'selected' : ''; ?>>B+</option>
                            <option value="B-" <?php echo $blood_group == 'B-' ? 'selected' : ''; ?>>B-</option>
                            <option value="AB+" <?php echo $blood_group == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                            <option value="AB-" <?php echo $blood_group == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                        </select>
                    </div>
                </div>
                
                <div class="section-title">Hospital Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="hospital_number">Hospital Number (PHN)</label>
                        <input type="text" id="hospital_number" name="hospital_number" value="<?php echo $hospital_number; ?>">
                    </div>
                    <div class="form-group">
                        <label for="clinic_number">Clinic Number</label>
                        <input type="text" id="clinic_number" name="clinic_number" value="<?php echo $clinic_number; ?>">
                    </div>
                </div>
                
                <div class="section-title">Contact Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_number">Contact Number *</label>
                        <input type="tel" id="contact_number" name="contact_number" value="<?php echo $contact_number; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address *</label>
                    <textarea id="address" name="address" rows="3" required><?php echo $address; ?></textarea>
                </div>
                
                <div class="section-title">Guardian Information</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="guardian_name">Guardian Name</label>
                        <input type="text" id="guardian_name" name="guardian_name" value="<?php echo $guardian_name; ?>">
                    </div>
                    <div class="form-group">
                        <label for="guardian_contact_number">Guardian Contact Number</label>
                        <input type="tel" id="guardian_contact_number" name="guardian_contact_number" value="<?php echo $guardian_contact_number; ?>">
                    </div>
                </div>
                
                <h3 style="color: #2c3e50; margin: 2rem 0 1rem 0; font-size: 1.25rem; border-bottom: 2px solid #3498db; padding-bottom: 0.5rem;">👩‍⚕️ Nursing Assignment</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="assigned_nursing_officer">Assigned Nursing Officer</label>
                        <select id="assigned_nursing_officer" name="assigned_nursing_officer">
                            <option value="">Select Nursing Officer (Optional)</option>
                            <?php while ($nursing_officer = $nursing_officers_result->fetch_assoc()): ?>
                                <option value="<?php echo $nursing_officer['nursing_id']; ?>" 
                                        <?php echo ($assigned_nursing_officer == $nursing_officer['nursing_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($nursing_officer['nursing_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">Save Patient</button>
                    <a href="patient_list.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
