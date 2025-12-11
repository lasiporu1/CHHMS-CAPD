<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get admission ID from URL
if (!isset($_GET['admission_id']) || empty($_GET['admission_id'])) {
    header("Location: admission_list.php");
    exit();
}

$admission_id = $conn->real_escape_string($_GET['admission_id']);

// Initialize variables
$report_id = $report_type = $report_title = $report_date = $report_time = '';
$report_content = $findings = $recommendations = $follow_up_date = $status = 'Draft';
$follow_up_required = 0;
$error = '';

// Check if editing
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM reports WHERE report_id = $id AND admission_id = $admission_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $report = $result->fetch_assoc();
        $report_id = $report['report_id'];
        $report_type = $report['report_type'];
        $report_title = $report['report_title'];
        $report_date = $report['report_date'];
        $report_time = $report['report_time'];
        $report_content = $report['report_content'];
        $findings = $report['findings'];
        $recommendations = $report['recommendations'];
        $follow_up_required = $report['follow_up_required'];
        $follow_up_date = $report['follow_up_date'];
        $status = $report['status'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $report_type = $conn->real_escape_string($_POST['report_type']);
    $report_title = $conn->real_escape_string($_POST['report_title']);
    $report_date = $conn->real_escape_string($_POST['report_date']);
    $report_time = $conn->real_escape_string($_POST['report_time']);
    $report_content = $conn->real_escape_string($_POST['report_content']);
    $findings = $conn->real_escape_string($_POST['findings']);
    $recommendations = $conn->real_escape_string($_POST['recommendations']);
    $follow_up_required = isset($_POST['follow_up_required']) ? 1 : 0;
    $follow_up_date = !empty($_POST['follow_up_date']) ? $conn->real_escape_string($_POST['follow_up_date']) : 'NULL';
    $status = $conn->real_escape_string($_POST['status']);
    
    // Validation
    if (empty($report_type)) {
        $error = "Report type is required!";
    } elseif (empty($report_title)) {
        $error = "Report title is required!";
    } elseif (empty($report_date)) {
        $error = "Report date is required!";
    } elseif (empty($report_time)) {
        $error = "Report time is required!";
    } elseif (empty($report_content)) {
        $error = "Report content is required!";
    } else {
        // Get admission date for validation
        $admission_check_sql = "SELECT admission_date FROM ward_admissions WHERE admission_id = $admission_id";
        $admission_result_check = $conn->query($admission_check_sql);
        $admission_data = $admission_result_check->fetch_assoc();
        
        // Validate report date is not earlier than admission date
        if ($report_date < $admission_data['admission_date']) {
            $error = "Report date cannot be earlier than admission date (" . date('M j, Y', strtotime($admission_data['admission_date'])) . ").";
        } else {
        if (!empty($report_id)) {
            // Update existing report
            $sql = "UPDATE reports SET 
                    report_type = '$report_type',
                    report_title = '$report_title',
                    report_date = '$report_date',
                    report_time = '$report_time',
                    report_content = '$report_content',
                    findings = '$findings',
                    recommendations = '$recommendations',
                    follow_up_required = $follow_up_required,
                    follow_up_date = " . ($follow_up_date !== 'NULL' ? "'$follow_up_date'" : 'NULL') . ",
                    status = '$status'
                    WHERE report_id = $report_id";
        } else {
            // Create new report
            $created_by = $_SESSION['user_id'];
            $sql = "INSERT INTO reports 
                    (admission_id, report_type, report_title, report_date, report_time, 
                     created_by, report_content, findings, recommendations, follow_up_required, 
                     follow_up_date, status) 
                    VALUES ($admission_id, '$report_type', '$report_title', '$report_date', '$report_time', 
                            $created_by, '$report_content', '$findings', '$recommendations', $follow_up_required, " . 
                            ($follow_up_date !== 'NULL' ? "'$follow_up_date'" : 'NULL') . ", '$status')";
        }
        
            if ($conn->query($sql) === TRUE) {
                header("Location: reports.php?admission_id=$admission_id");
                exit();
            } else {
                $error = "Error saving report: " . $conn->error;
            }
        }
    }
}

// Get admission details
$admission_sql = "SELECT wa.*, p.calling_name, p.full_name, p.nic 
                  FROM ward_admissions wa
                  LEFT JOIN patients p ON wa.patient_id = p.patient_id
                  WHERE wa.admission_id = $admission_id";
$admission_result = $conn->query($admission_sql);
$admission = $admission_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($report_id) ? 'Edit' : 'Create'; ?> Report</title>
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
        
        .patient-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #3498db;
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
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
            <a href="reports.php?admission_id=<?php echo $admission_id; ?>">Reports</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo !empty($report_id) ? '✏️ Edit' : '📊 Create'; ?> Medical Report</h2>
            
            <!-- Patient Information -->
            <div class="patient-info">
                <h3 style="margin-bottom: 1rem; color: #2c3e50;">👤 Patient Information</h3>
                <strong>Patient:</strong> <?php echo htmlspecialchars($admission['calling_name']) . ' (' . htmlspecialchars($admission['full_name']) . ')'; ?><br>
                <strong>NIC:</strong> <?php echo htmlspecialchars($admission['nic']); ?> | 
                <strong>Admission ID:</strong> #<?php echo str_pad($admission_id, 4, '0', STR_PAD_LEFT); ?>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="section-title">📊 Report Details</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="report_type">Report Type *</label>
                        <select id="report_type" name="report_type" required>
                            <option value="">Select Type</option>
                            <option value="Progress Note" <?php echo ($report_type == 'Progress Note') ? 'selected' : ''; ?>>Progress Note</option>
                            <option value="Discharge Summary" <?php echo ($report_type == 'Discharge Summary') ? 'selected' : ''; ?>>Discharge Summary</option>
                            <option value="Consultation Report" <?php echo ($report_type == 'Consultation Report') ? 'selected' : ''; ?>>Consultation Report</option>
                            <option value="Medical Assessment" <?php echo ($report_type == 'Medical Assessment') ? 'selected' : ''; ?>>Medical Assessment</option>
                            <option value="Peritonitis Report" <?php echo ($report_type == 'Peritonitis Report') ? 'selected' : ''; ?>>Peritonitis Report</option>
                            <option value="Dialysis Summary" <?php echo ($report_type == 'Dialysis Summary') ? 'selected' : ''; ?>>Dialysis Summary</option>
                            <option value="Weekly Review" <?php echo ($report_type == 'Weekly Review') ? 'selected' : ''; ?>>Weekly Review</option>
                            <option value="Other" <?php echo ($report_type == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="Draft" <?php echo ($status == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="Completed" <?php echo ($status == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Reviewed" <?php echo ($status == 'Reviewed') ? 'selected' : ''; ?>>Reviewed</option>
                            <option value="Archived" <?php echo ($status == 'Archived') ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="report_title">Report Title *</label>
                    <input type="text" id="report_title" name="report_title" value="<?php echo htmlspecialchars($report_title); ?>" required placeholder="Brief title describing the report">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="report_date">Report Date *</label>
                        <input type="date" id="report_date" name="report_date" value="<?php echo $report_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="report_time">Report Time *</label>
                        <input type="time" id="report_time" name="report_time" value="<?php echo $report_time; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="report_content">Report Content *</label>
                    <textarea id="report_content" name="report_content" required placeholder="Main content of the medical report..." style="min-height: 150px;"><?php echo htmlspecialchars($report_content); ?></textarea>
                </div>
                
                <div class="section-title">🔍 Clinical Assessment</div>
                
                <div class="form-group">
                    <label for="findings">Clinical Findings</label>
                    <textarea id="findings" name="findings" placeholder="Key clinical findings and observations..."><?php echo htmlspecialchars($findings); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="recommendations">Recommendations</label>
                    <textarea id="recommendations" name="recommendations" placeholder="Treatment recommendations and plan..."><?php echo htmlspecialchars($recommendations); ?></textarea>
                </div>
                
                <div class="section-title">📅 Follow-up</div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="follow_up_required" name="follow_up_required" value="1" <?php echo $follow_up_required ? 'checked' : ''; ?>>
                    <label for="follow_up_required" style="margin: 0; text-transform: none; color: #3498db; font-weight: bold;">📅 Follow-up Required</label>
                </div>
                
                <div class="form-group">
                    <label for="follow_up_date">Follow-up Date</label>
                    <input type="date" id="follow_up_date" name="follow_up_date" value="<?php echo $follow_up_date !== 'NULL' ? $follow_up_date : ''; ?>">
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <?php echo !empty($report_id) ? '💾 Update Report' : '📊 Create Report'; ?>
                    </button>
                    <a href="reports.php?admission_id=<?php echo $admission_id; ?>" class="btn btn-secondary">← Back to List</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to admission date
            const admissionDate = '<?php echo $admission['admission_date']; ?>';
            const reportDateField = document.getElementById('report_date');
            reportDateField.setAttribute('min', admissionDate);
            
            // Set default date and time
            if (!reportDateField.value) {
                reportDateField.value = new Date().toISOString().split('T')[0];
            }
            if (!document.getElementById('report_time').value) {
                const now = new Date();
                const timeString = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                document.getElementById('report_time').value = timeString;
            }
        });
    </script>
</body>
</html>