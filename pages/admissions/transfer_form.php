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

$transfer_date = $transfer_time = $transfer_ward = $transfer_reason = $transfer_notes = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $transfer_date = $conn->real_escape_string($_POST['transfer_date']);
    $transfer_time = $conn->real_escape_string($_POST['transfer_time']);
    $transfer_ward = $conn->real_escape_string($_POST['transfer_ward']);
    $transfer_reason = $conn->real_escape_string($_POST['transfer_reason']);
    $transfer_notes = $conn->real_escape_string($_POST['transfer_notes']);
    
    // Validation
    if (empty($transfer_date)) {
        $error = "Transfer date is required!";
    } elseif (empty($transfer_time)) {
        $error = "Transfer time is required!";
    } elseif (empty($transfer_ward)) {
        $error = "Transfer ward/department is required!";
    } elseif (empty($transfer_reason)) {
        $error = "Transfer reason is required!";
    } elseif (strtotime($transfer_date . ' ' . $transfer_time) < strtotime($admission['admission_date'] . ' ' . $admission['admission_time'])) {
        $error = "Transfer date/time cannot be before admission date/time!";
    } else {
        // Update admission record
        $combined_notes = "TRANSFERRED TO: " . $transfer_ward . "\n";
        $combined_notes .= "TRANSFER REASON: " . $transfer_reason . "\n";
        $combined_notes .= "TRANSFER DATE/TIME: " . date('M j, Y g:i A', strtotime($transfer_date . ' ' . $transfer_time)) . "\n";
        if (!empty($transfer_notes)) {
            $combined_notes .= "TRANSFER NOTES: " . $transfer_notes;
        }
        
        $sql = "UPDATE ward_admissions SET 
                admission_status = 'Transferred',
                discharge_date = '$transfer_date',
                discharge_time = '$transfer_time',
                discharge_notes = '$combined_notes',
                updated_at = CURRENT_TIMESTAMP
                WHERE admission_id = $admission_id";
        
        if ($conn->query($sql) === TRUE) {
            header("Location: admission_view.php?id=$admission_id");
            exit();
        } else {
            $error = "Error transferring patient: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Patient - <?php echo htmlspecialchars($admission['calling_name']); ?></title>
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
            background: linear-gradient(135deg, #f39c12, #e67e22);
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
            border-color: #f39c12;
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.1);
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
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
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
                <h2>🚚 Transfer Patient</h2>
                <p>Transfer this patient to another ward or department</p>
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
                    <p>Once you transfer this patient, their admission status will be changed to "Transferred" and they will no longer be under Ward care. Please ensure all necessary handover procedures have been completed.</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="section-title">🚚 Transfer Information</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="transfer_date">Transfer Date *</label>
                            <input type="date" id="transfer_date" name="transfer_date" value="<?php echo $transfer_date; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="transfer_time">Transfer Time *</label>
                            <input type="time" id="transfer_time" name="transfer_time" value="<?php echo $transfer_time; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="transfer_ward">Transfer To Ward/Department *</label>
                        <select id="transfer_ward" name="transfer_ward" required>
                            <option value="">Select Ward/Department</option>
                            <option value="ICU">Intensive Care Unit (ICU)</option>
                            <option value="CCU">Coronary Care Unit (CCU)</option>
                            <option value="Medical Ward">Medical Ward</option>
                            <option value="Surgical Ward">Surgical Ward</option>
                            <option value="Orthopedic Ward">Orthopedic Ward</option>
                            <option value="Pediatric Ward">Pediatric Ward</option>
                            <option value="Maternity Ward">Maternity Ward</option>
                            <option value="Emergency Department">Emergency Department</option>
                            <option value="Nephrology Ward">Nephrology Ward</option>
                            <option value="Cardiology Ward">Cardiology Ward</option>
                            <option value="Neurology Ward">Neurology Ward</option>
                            <option value="Oncology Ward">Oncology Ward</option>
                            <option value="Dialysis Unit">Dialysis Unit</option>
                            <option value="Operating Theater">Operating Theater</option>
                            <option value="Other Hospital">Other Hospital</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="transfer_reason">Transfer Reason *</label>
                        <select id="transfer_reason" name="transfer_reason" required>
                            <option value="">Select Transfer Reason</option>
                            <option value="Requires Specialized Care">Requires Specialized Care</option>
                            <option value="Medical Emergency">Medical Emergency</option>
                            <option value="Patient Request">Patient Request</option>
                            <option value="Treatment Completed">Treatment Completed</option>
                            <option value="Bed Management">Bed Management</option>
                            <option value="Surgical Intervention Required">Surgical Intervention Required</option>
                            <option value="Intensive Monitoring Required">Intensive Monitoring Required</option>
                            <option value="Complications Developed">Complications Developed</option>
                            <option value="Administrative Decision">Administrative Decision</option>
                            <option value="Referral to Specialist">Referral to Specialist</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="transfer_notes">Transfer Notes</label>
                        <textarea id="transfer_notes" name="transfer_notes" placeholder="Enter handover notes, current condition, medications, special instructions, etc..."><?php echo htmlspecialchars($transfer_notes); ?></textarea>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to transfer this patient? This action cannot be undone.')">
                            🚚 Transfer Patient
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
            // Set default transfer date and time to current
            if (!document.getElementById('transfer_date').value) {
                document.getElementById('transfer_date').value = new Date().toISOString().split('T')[0];
            }
            if (!document.getElementById('transfer_time').value) {
                const now = new Date();
                const timeString = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                document.getElementById('transfer_time').value = timeString;
            }
        });
    </script>
</body>
</html>