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

// Fetch admission details with joined tables
$sql = "SELECT wa.*, 
               p.calling_name, p.full_name, p.nic, p.hospital_number, p.clinic_number,
               p.date_of_birth, p.contact_number, p.address,
               ar.reason_name, ar.description as reason_description,
               d.doctor_name, d.specialization, no.nursing_name,
               u.username as created_by_name
        FROM ward_admissions wa
        LEFT JOIN patients p ON wa.patient_id = p.patient_id
        LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
        LEFT JOIN doctors d ON wa.attending_doctor_id = d.doctor_id
        LEFT JOIN nursing_officers no ON wa.nursing_officer_id = no.nursing_id
        LEFT JOIN users u ON wa.created_by = u.user_id
        WHERE wa.admission_id = $admission_id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: admission_list.php");
    exit();
}

$admission = $result->fetch_assoc();

// Calculate age from date of birth
$age = '';
if (!empty($admission['date_of_birth'])) {
    $dob = new DateTime($admission['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y . ' years';
}

// Calculate admission duration
$admission_duration = '';
if (!empty($admission['admission_date'])) {
    $admission_datetime = new DateTime($admission['admission_date'] . ' ' . $admission['admission_time']);
    $now = new DateTime();
    
    if ($admission['admission_status'] == 'Discharged' && !empty($admission['discharge_date'])) {
        $discharge_datetime = new DateTime($admission['discharge_date'] . ' ' . $admission['discharge_time']);
        $duration = $admission_datetime->diff($discharge_datetime);
    } else {
        $duration = $admission_datetime->diff($now);
    }
    
    if ($duration->days > 0) {
        $admission_duration = $duration->days . ' days, ' . $duration->h . ' hours';
    } else {
        $admission_duration = $duration->h . ' hours, ' . $duration->i . ' minutes';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Details - <?php echo htmlspecialchars($admission['calling_name']); ?></title>
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
        
        .header-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .patient-info h2 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .admission-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .status-discharged {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .status-transferred {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .status-complete {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .status-incomplete {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            padding: 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 150px;
        }
        
        .info-value {
            color: #34495e;
            flex: 1;
            text-align: right;
        }
        
        .full-width {
            grid-column: 1 / -1;
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
            margin-right: 1rem;
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
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }
        
        .action-buttons {
            margin-top: 2rem;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .notes-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 4px solid #3498db;
        }
        
        .notes-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .notes-content {
            color: #555;
            line-height: 1.6;
        }
        
        .notes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .notes-author {
            font-size: 0.85rem;
            color: #7f8c8d;
            font-style: italic;
        }
        
        .notes-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .notes-author-info {
            font-size: 0.85rem;
            color: #7f8c8d;
            font-style: italic;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        @media (max-width: 768px) {
            .notes-grid {
                grid-template-columns: 1fr;
            }
            .grid-container {
                grid-template-columns: 1fr;
            }
            
            .header-card {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .action-buttons {
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
            <a href="admission_reasons_list.php">Admission Reasons</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Header Card with Patient Info and Status -->
        <div class="header-card">
            <div class="patient-info">
                <h2>👤 <?php echo htmlspecialchars($admission['calling_name']); ?></h2>
                <p style="color: #666; font-size: 1.1rem;"><?php echo htmlspecialchars($admission['full_name']); ?></p>
                <p style="color: #888;">Admission ID: #<?php echo $admission['admission_id']; ?>
                <?php
                // Show death date if patient is deceased
                $death_q = $conn->query("SELECT patient_status, death_date FROM patients WHERE patient_id = " . intval($admission['patient_id']));
                if ($death_q && $death_row = $death_q->fetch_assoc()) {
                    if (isset($death_row['patient_status']) && strtolower($death_row['patient_status']) === 'deceased' && !empty($death_row['death_date'])) {
                        echo '<span style="color:#c0392b;font-size:0.85rem;font-weight:bold;margin-left:10px;">Death: ' . htmlspecialchars(date('Y-m-d', strtotime($death_row['death_date']))) . '</span>';
                    }
                }
                ?>
                </p>
            </div>
            <div class="admission-status status-<?php echo strtolower($admission['admission_status']); ?>">
                <?php echo $admission['admission_status']; ?>
            </div>
        </div>
        
        <div class="grid-container">
            <!-- Patient Information -->
            <div class="card">
                <div class="card-header">
                    👤 Patient Information
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($admission['full_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Calling Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($admission['calling_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">NIC:</span>
                        <span class="info-value"><?php echo htmlspecialchars($admission['nic']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Age:</span>
                        <span class="info-value"><?php echo $age ? $age : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Hospital Number:</span>
                        <span class="info-value"><?php echo $admission['hospital_number'] ? htmlspecialchars($admission['hospital_number']) : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Clinic Number:</span>
                        <span class="info-value"><?php echo $admission['clinic_number'] ? htmlspecialchars($admission['clinic_number']) : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact Number:</span>
                        <span class="info-value"><?php echo $admission['contact_number'] ? htmlspecialchars($admission['contact_number']) : 'N/A'; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Admission Details -->
            <div class="card">
                <div class="card-header">
                    🏥 Admission Details
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">BHT Number:</span>
                        <span class="info-value"><?php echo $admission['bht_number'] ? htmlspecialchars($admission['bht_number']) : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Admission Reason:</span>
                        <span class="info-value"><?php echo htmlspecialchars($admission['reason_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Admission Date:</span>
                        <span class="info-value"><?php echo date('d M Y', strtotime($admission['admission_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Admission Time:</span>
                        <span class="info-value"><?php echo date('g:i A', strtotime($admission['admission_time'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ward Bed:</span>
                        <span class="info-value"><?php echo $admission['ward_bed'] ? htmlspecialchars($admission['ward_bed']) : 'Not assigned'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Duration:</span>
                        <span class="info-value"><?php echo $admission_duration; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="admission-status status-<?php echo strtolower($admission['admission_status']); ?>" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                <?php echo $admission['admission_status']; ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Medical Staff -->
            <div class="card">
                <div class="card-header">
                    👨‍⚕️ Medical Staff
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Attending Doctor:</span>
                        <span class="info-value">
                            <?php 
                            if ($admission['doctor_name']) {
                                echo htmlspecialchars($admission['doctor_name']);
                                if (!empty($admission['specialization'])) {
                                    echo '<br><small style="color: #666;">Specialization: ' . htmlspecialchars($admission['specialization']) . '</small>';
                                }
                            } else {
                                echo 'Not assigned';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nursing Officer:</span>
                        <span class="info-value"><?php echo $admission['nursing_name'] ? htmlspecialchars($admission['nursing_name']) : 'Not assigned'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Admitted By:</span>
                        <span class="info-value"><?php echo htmlspecialchars($admission['created_by_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created At:</span>
                        <span class="info-value"><?php echo date('d M Y g:i A', strtotime($admission['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Discharge Information (if discharged) -->
            <?php if (!empty($admission['discharge_date']) || !empty($admission['discharge_status'])): ?>
            <div class="card">
                <div class="card-header">
                    📋 Discharge Information
                </div>
                <div class="card-body">
                    <?php if (!empty($admission['discharge_status'])): ?>
                    <div class="info-row">
                        <span class="info-label">Discharge Status:</span>
                        <span class="info-value">
                            <span class="admission-status status-<?php echo strtolower(str_replace(' ', '-', $admission['discharge_status'])); ?>" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                <?php echo htmlspecialchars($admission['discharge_status']); ?>
                            </span>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Discharge Date:</span>
                        <span class="info-value"><?php echo $admission['discharge_date'] ? date('d M Y', strtotime($admission['discharge_date'])) : 'Not discharged'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Discharge Time:</span>
                        <span class="info-value"><?php echo $admission['discharge_time'] ? date('g:i A', strtotime($admission['discharge_time'])) : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Discharged By:</span>
                        <span class="info-value"><?php echo !empty($admission['created_by_name']) ? htmlspecialchars($admission['created_by_name']) : 'System User'; ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Notes Section -->
        <?php 
        $has_discharge_notes = !empty($admission['discharge_note']) || !empty($admission['discharge_notes']) || !empty($admission['notes']);
        if (!empty($admission['admission_notes']) || $has_discharge_notes): ?>
        <div class="notes-grid">
            <!-- Admission Notes -->
            <?php if (!empty($admission['admission_notes'])): ?>
            <div class="card">
                <div class="card-header">
                    📝 Admission Notes
                </div>
                <div class="card-body">
                    <div class="notes-content"><?php echo nl2br(htmlspecialchars($admission['admission_notes'])); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Discharge Notes -->
            <?php 
            $discharge_notes = '';
            if (!empty($admission['discharge_note'])) {
                $discharge_notes = $admission['discharge_note'];
            } elseif (!empty($admission['discharge_notes'])) {
                $discharge_notes = $admission['discharge_notes'];
            } elseif (!empty($admission['notes'])) {
                $discharge_notes = $admission['notes'];
            }
            
            if (!empty($discharge_notes)): ?>
            <div class="card">
                <div class="card-header">
                    📋 Discharge Notes
                </div>
                <div class="card-body">
                    <div class="notes-author-info">By: <?php echo !empty($admission['created_by_name']) ? htmlspecialchars($admission['created_by_name']) : 'System User'; ?></div>
                    <div class="notes-content"><?php echo nl2br(htmlspecialchars($discharge_notes)); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Reason Description -->
        <?php if (!empty($admission['reason_description'])): ?>
        <div class="card full-width">
            <div class="card-header">
                ℹ️ Admission Reason Details
            </div>
            <div class="card-body">
                <div class="notes-content"><?php echo nl2br(htmlspecialchars($admission['reason_description'])); ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="admission_form.php?edit=<?php echo $admission['admission_id']; ?>" class="btn btn-primary">
                ✏️ Edit Admission
            </a>
            
            <?php if ($admission['admission_status'] == 'Active'): ?>
                <a href="#" onclick="dischargePatient()" class="btn btn-warning">
                    📋 Discharge Patient
                </a>
                <a href="#" onclick="transferPatient()" class="btn btn-success">
                    🚚 Transfer Patient
                </a>
            <?php endif; ?>
            
            <a href="investigations.php?admission_id=<?php echo $admission['admission_id']; ?>" class="btn btn-success">
                🔬 Investigations
            </a>
            <a href="medicines.php?admission_id=<?php echo $admission['admission_id']; ?>" class="btn btn-warning">
                💊 Medicines
            </a>
            
            <a href="patient_report.php?admission_id=<?php echo $admission['admission_id']; ?>" class="btn btn-primary">
                📋 Full Patient Report
            </a>
            
            <a href="admission_list.php" class="btn btn-secondary">
                ← Back to List
            </a>
        </div>
    </div>
    
    <script>
        function dischargePatient() {
            if (confirm('Are you sure you want to discharge this patient?')) {
                // Implement discharge functionality
                window.location.href = 'discharge_form.php?id=<?php echo $admission["admission_id"]; ?>';
            }
        }
        
        function transferPatient() {
            if (confirm('Are you sure you want to transfer this patient?')) {
                // Implement transfer functionality
                window.location.href = 'transfer_form.php?id=<?php echo $admission["admission_id"]; ?>';
            }
        }
    </script>
</body>
</html>