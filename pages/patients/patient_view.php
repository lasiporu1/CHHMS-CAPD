<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$patient = null;

if (isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    
    // Add nursing officer column if it doesn't exist
    $check_column = "SHOW COLUMNS FROM patients LIKE 'assigned_nursing_officer'";
    $column_exists = $conn->query($check_column);
    if ($column_exists->num_rows == 0) {
        $alter_sql = "ALTER TABLE patients ADD COLUMN assigned_nursing_officer INT NULL";
        $conn->query($alter_sql);
    }
    
    $sql = "SELECT p.*, no.nursing_name, no.contact_number as nursing_contact
            FROM patients p 
            LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id
            WHERE p.patient_id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
    }
}

if (!$patient) {
    header("Location: patient_list.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .navbar {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .section-title {
            background-color: #34495e;
            color: white;
            padding: 0.75rem;
            margin: 1.5rem 0 1rem 0;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
        }
        
        .info-item-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .info-item-value {
            color: #555;
        }
        
        .death-status-alert {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            border-left: 6px solid #d32f2f;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(211, 47, 47, 0.2);
        }
        
        .death-status-alert h3 {
            color: #c62828;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        
        .death-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .death-info-item {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid #d32f2f;
        }
        
        .death-info-label {
            font-weight: 600;
            color: #d32f2f;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .death-info-value {
            color: #2c3e50;
            font-weight: 600;
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Patient Details</h2>
                <div>
                    <a href="patient_form.php?edit=<?php echo $patient['patient_id']; ?>" class="btn btn-primary">Edit</a>
                    <a href="patient_list.php?delete=<?php echo $patient['patient_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this patient? This action cannot be undone.');">Delete</a>
                    <a href="patient_list.php" class="btn btn-secondary">Back</a>
                </div>
            </div>
            
            <?php if (isset($patient['patient_status']) && $patient['patient_status'] == 'Deceased'): ?>
            <div class="death-status-alert">
                <h3>⚰️ Patient Deceased</h3>
                <div class="death-info-grid">
                    <?php if (!empty($patient['death_date'])): ?>
                    <div class="death-info-item">
                        <div class="death-info-label">Date of Death</div>
                        <div class="death-info-value"><?php echo date('F d, Y', strtotime($patient['death_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($patient['death_notes'])): ?>
                    <div class="death-info-item" style="grid-column: span 2;">
                        <div class="death-info-label">Death Notes / Cause of Death</div>
                        <div class="death-info-value"><?php echo nl2br(htmlspecialchars($patient['death_notes'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="section-title">Personal Information</div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-item-label">Calling Name</div>
                    <div class="info-item-value"><?php echo $patient['calling_name']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Full Name</div>
                    <div class="info-item-value"><?php echo $patient['full_name']; ?></div>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-item-label">National Identity Card (NIC)</div>
                    <div class="info-item-value"><?php echo $patient['nic']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Date of Birth</div>
                    <div class="info-item-value"><?php echo date('F d, Y', strtotime($patient['date_of_birth'])); ?></div>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-item-label">Gender</div>
                    <div class="info-item-value"><?php echo $patient['sex']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Blood Group</div>
                    <div class="info-item-value"><?php echo $patient['blood_group'] ? $patient['blood_group'] : 'Not Specified'; ?></div>
                </div>
            </div>
            
            <div class="section-title">Hospital Information</div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-item-label">Hospital Number (PHN)</div>
                    <div class="info-item-value"><?php echo $patient['hospital_number'] ? $patient['hospital_number'] : 'Not Assigned'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Clinic Number</div>
                    <div class="info-item-value"><?php echo $patient['clinic_number'] ? $patient['clinic_number'] : 'Not Assigned'; ?></div>
                </div>
            </div>
            
            <div class="section-title">Contact Information</div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-item-label">Contact Number</div>
                    <div class="info-item-value"><?php echo $patient['contact_number']; ?></div>
                </div>
            </div>
            
            <div class="info-item" style="margin-bottom: 1rem;">
                <div class="info-item-label">Address</div>
                <div class="info-item-value"><?php echo nl2br($patient['address']); ?></div>
            </div>
            
            <div class="section-title">Guardian Information</div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-item-label">Guardian Name</div>
                    <div class="info-item-value"><?php echo $patient['guardian_name'] ? $patient['guardian_name'] : 'Not Specified'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Guardian Contact Number</div>
                    <div class="info-item-value"><?php echo $patient['guardian_contact_number'] ? $patient['guardian_contact_number'] : 'Not Specified'; ?></div>
                </div>
            </div>
            
            <div class="section-title">👩‍⚕️ Nursing Assignment</div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-item-label">Assigned Nursing Officer</div>
                    <div class="info-item-value">
                        <?php if ($patient['nursing_name']): ?>
                            <span style="background: linear-gradient(135deg, #e8f5e8, #c3e6cb); color: #2d6a2d; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; display: inline-block;">
                                👩‍⚕️ <?php echo htmlspecialchars($patient['nursing_name']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;">No nursing officer assigned</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($patient['nursing_contact']): ?>
                <div class="info-item">
                    <div class="info-item-label">Nursing Officer Contact</div>
                    <div class="info-item-value">
                        <span style="color: #2c3e50; font-weight: 500;"><?php echo htmlspecialchars($patient['nursing_contact']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="section-title">System Information</div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-item-label">Created Date</div>
                    <div class="info-item-value"><?php echo date('F d, Y g:i A', strtotime($patient['created_at'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Last Updated</div>
                    <div class="info-item-value"><?php echo date('F d, Y g:i A', strtotime($patient['updated_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
