<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Create medicines table if it doesn't exist with all required columns
$create_table_sql = "CREATE TABLE IF NOT EXISTS medicines (
    medicine_id INT AUTO_INCREMENT PRIMARY KEY,
    admission_id INT NOT NULL,
    medicine_name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255) NULL,
    dosage VARCHAR(100) NOT NULL,
    route VARCHAR(50) NOT NULL,
    frequency VARCHAR(100) NOT NULL,
    duration VARCHAR(50) NULL,
    start_date DATE NOT NULL,
    start_time TIME NULL,
    end_date DATE NULL,
    prescribed_by INT NOT NULL,
    indication TEXT NULL,
    instructions TEXT NULL,
    side_effects TEXT NULL,
    contraindications TEXT NULL,
    status ENUM('Active', 'Discontinued', 'Completed') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Add missing columns to existing medicines table if they don't exist
$columns_to_add = [
    "generic_name VARCHAR(255) NULL",
    "duration VARCHAR(50) NULL",
    "start_time TIME NULL",
    "indication TEXT NULL",
    "instructions TEXT NULL", 
    "side_effects TEXT NULL",
    "contraindications TEXT NULL"
];

foreach ($columns_to_add as $column) {
    $column_name = explode(' ', $column)[0];
    $check_column = "SHOW COLUMNS FROM medicines LIKE '$column_name'";
    $column_exists = $conn->query($check_column);
    
    if ($column_exists->num_rows == 0) {
        $alter_sql = "ALTER TABLE medicines ADD COLUMN $column";
        $conn->query($alter_sql);
    }
}

// Get admission ID from URL
if (!isset($_GET['admission_id']) || empty($_GET['admission_id'])) {
    header("Location: admission_list.php");
    exit();
}

$admission_id = $conn->real_escape_string($_GET['admission_id']);

// Initialize variables
$medicine_id = $medicine_name = $generic_name = $dosage = $route = $frequency = '';
$duration = $start_date = $start_time = $end_date = $indication = '';
$instructions = $side_effects = $contraindications = $status = 'Active';
$error = '';

// Check if editing
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM medicines WHERE medicine_id = $id AND admission_id = $admission_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $medicine = $result->fetch_assoc();
        $medicine_id = $medicine['medicine_id'];
        $medicine_name = $medicine['medicine_name'];
        $generic_name = $medicine['generic_name'];
        $dosage = $medicine['dosage'];
        $route = $medicine['route'];
        $frequency = $medicine['frequency'];
        $duration = $medicine['duration'];
        $start_date = $medicine['start_date'];
        $start_time = $medicine['start_time'];
        $end_date = $medicine['end_date'];
        $indication = $medicine['indication'];
        $instructions = $medicine['instructions'];
        $side_effects = $medicine['side_effects'];
        $contraindications = $medicine['contraindications'];
        $status = $medicine['status'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $medicine_name = $conn->real_escape_string($_POST['medicine_name']);
    $generic_name = $conn->real_escape_string($_POST['generic_name']);
    $dosage = $conn->real_escape_string($_POST['dosage']);
    $route = $conn->real_escape_string($_POST['route']);
    $frequency = $conn->real_escape_string($_POST['frequency']);
    $duration = $conn->real_escape_string($_POST['duration']);
    $start_date = $conn->real_escape_string($_POST['start_date']);
    $start_time = $conn->real_escape_string($_POST['start_time']);
    $end_date = !empty($_POST['end_date']) ? $conn->real_escape_string($_POST['end_date']) : 'NULL';
    $indication = $conn->real_escape_string($_POST['indication']);
    $instructions = $conn->real_escape_string($_POST['instructions']);
    $side_effects = $conn->real_escape_string($_POST['side_effects']);
    $contraindications = $conn->real_escape_string($_POST['contraindications']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // Validation
    if (empty($medicine_name)) {
        $error = "Medicine name is required!";
    } elseif (empty($dosage)) {
        $error = "Dosage is required!";
    } elseif (empty($route)) {
        $error = "Route is required!";
    } elseif (empty($frequency)) {
        $error = "Frequency is required!";
    } elseif (empty($start_date)) {
        $error = "Start date is required!";
    } elseif (empty($start_time)) {
        $error = "Start time is required!";
    } else {
        // Get admission date for validation
        $admission_check_sql = "SELECT admission_date FROM ward_admissions WHERE admission_id = $admission_id";
        $admission_result_check = $conn->query($admission_check_sql);
        $admission_data = $admission_result_check->fetch_assoc();
        
        // Validate start date is not earlier than admission date
        if ($start_date < $admission_data['admission_date']) {
            $error = "Medicine start date cannot be earlier than admission date (" . date('M j, Y', strtotime($admission_data['admission_date'])) . ").";
        } elseif (!empty($_POST['end_date']) && $_POST['end_date'] < $start_date) {
            $error = "Schedule end date cannot be earlier than schedule start date (" . date('M j, Y', strtotime($start_date)) . ").";
        } else {
        if (!empty($medicine_id)) {
            // Update existing medicine
            $sql = "UPDATE medicines SET 
                    medicine_name = '$medicine_name',
                    generic_name = '$generic_name',
                    dosage = '$dosage',
                    route = '$route',
                    frequency = '$frequency',
                    duration = '$duration',
                    start_date = '$start_date',
                    start_time = '$start_time',
                    end_date = " . ($end_date !== 'NULL' ? "'$end_date'" : 'NULL') . ",
                    indication = '$indication',
                    instructions = '$instructions',
                    side_effects = '$side_effects',
                    contraindications = '$contraindications',
                    status = '$status'
                    WHERE medicine_id = $medicine_id";
        } else {
            // Create new medicine
            $prescribed_by = $_SESSION['user_id'];
            $sql = "INSERT INTO medicines 
                    (admission_id, medicine_name, generic_name, dosage, route, frequency, 
                     duration, start_date, start_time, end_date, prescribed_by, indication, 
                     instructions, side_effects, contraindications, status) 
                    VALUES ($admission_id, '$medicine_name', '$generic_name', '$dosage', '$route', '$frequency', 
                            '$duration', '$start_date', '$start_time', " . 
                            ($end_date !== 'NULL' ? "'$end_date'" : 'NULL') . ", $prescribed_by, '$indication', 
                            '$instructions', '$side_effects', '$contraindications', '$status')";
        }
        
            if ($conn->query($sql) === TRUE) {
                header("Location: medicines.php?admission_id=$admission_id");
                exit();
            } else {
                $error = "Error saving medicine: " . $conn->error;
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
    <title><?php echo !empty($medicine_id) ? 'Edit' : 'Prescribe'; ?> Medicine</title>
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
            border-bottom: 3px solid #f39c12;
            padding-bottom: 1rem;
        }
        
        .patient-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #f39c12;
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
            min-height: 100px;
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
            <a href="medicines.php?admission_id=<?php echo $admission_id; ?>">Medicines</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo !empty($medicine_id) ? '✏️ Edit' : '💊 Prescribe'; ?> Medicine</h2>
            
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
                <div class="section-title">💊 Medicine Details</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="medicine_name">Medicine Name *</label>
                        <input type="text" id="medicine_name" name="medicine_name" value="<?php echo htmlspecialchars($medicine_name); ?>" required placeholder="e.g., Paracetamol">
                    </div>
                    <div class="form-group">
                        <label for="generic_name">Generic Name</label>
                        <input type="text" id="generic_name" name="generic_name" value="<?php echo htmlspecialchars($generic_name); ?>" placeholder="e.g., Acetaminophen">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dosage">Dosage *</label>
                        <input type="text" id="dosage" name="dosage" value="<?php echo htmlspecialchars($dosage); ?>" required placeholder="e.g., 500mg">
                    </div>
                    <div class="form-group">
                        <label for="route">Route *</label>
                        <select id="route" name="route" required>
                            <option value="">Select Route</option>
                            <option value="Oral" <?php echo ($route == 'Oral') ? 'selected' : ''; ?>>Oral</option>
                            <option value="IV" <?php echo ($route == 'IV') ? 'selected' : ''; ?>>Intravenous (IV)</option>
                            <option value="IM" <?php echo ($route == 'IM') ? 'selected' : ''; ?>>Intramuscular (IM)</option>
                            <option value="SC" <?php echo ($route == 'SC') ? 'selected' : ''; ?>>Subcutaneous (SC)</option>
                            <option value="Topical" <?php echo ($route == 'Topical') ? 'selected' : ''; ?>>Topical</option>
                            <option value="Inhaled" <?php echo ($route == 'Inhaled') ? 'selected' : ''; ?>>Inhaled</option>
                            <option value="Rectal" <?php echo ($route == 'Rectal') ? 'selected' : ''; ?>>Rectal</option>
                            <option value="Other" <?php echo ($route == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="frequency">Frequency *</label>
                        <select id="frequency" name="frequency" required>
                            <option value="">Select Frequency</option>
                            <option value="Once daily" <?php echo ($frequency == 'Once daily') ? 'selected' : ''; ?>>Once daily</option>
                            <option value="Twice daily" <?php echo ($frequency == 'Twice daily') ? 'selected' : ''; ?>>Twice daily</option>
                            <option value="Three times daily" <?php echo ($frequency == 'Three times daily') ? 'selected' : ''; ?>>Three times daily</option>
                            <option value="Four times daily" <?php echo ($frequency == 'Four times daily') ? 'selected' : ''; ?>>Four times daily</option>
                            <option value="Every 4 hours" <?php echo ($frequency == 'Every 4 hours') ? 'selected' : ''; ?>>Every 4 hours</option>
                            <option value="Every 6 hours" <?php echo ($frequency == 'Every 6 hours') ? 'selected' : ''; ?>>Every 6 hours</option>
                            <option value="Every 8 hours" <?php echo ($frequency == 'Every 8 hours') ? 'selected' : ''; ?>>Every 8 hours</option>
                            <option value="Every 12 hours" <?php echo ($frequency == 'Every 12 hours') ? 'selected' : ''; ?>>Every 12 hours</option>
                            <option value="As needed" <?php echo ($frequency == 'As needed') ? 'selected' : ''; ?>>As needed (PRN)</option>
                            <option value="Custom" <?php echo ($frequency == 'Custom') ? 'selected' : ''; ?>>Custom</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="duration">Duration</label>
                        <input type="text" id="duration" name="duration" value="<?php echo htmlspecialchars($duration); ?>" placeholder="e.g., 7 days, 2 weeks">
                    </div>
                </div>
                
                <div class="section-title">📅 Schedule</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="start_time">Start Time *</label>
                        <input type="time" id="start_time" name="start_time" value="<?php echo $start_time; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="end_date">End Date (Optional)</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date !== 'NULL' ? $end_date : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="Active" <?php echo ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Completed" <?php echo ($status == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Discontinued" <?php echo ($status == 'Discontinued') ? 'selected' : ''; ?>>Discontinued</option>
                            <option value="On Hold" <?php echo ($status == 'On Hold') ? 'selected' : ''; ?>>On Hold</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="indication">Indication</label>
                    <textarea id="indication" name="indication" placeholder="What is this medicine prescribed for?"><?php echo htmlspecialchars($indication); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="instructions">Instructions</label>
                    <textarea id="instructions" name="instructions" placeholder="Special instructions for taking this medicine..."><?php echo htmlspecialchars($instructions); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="side_effects">Side Effects</label>
                    <textarea id="side_effects" name="side_effects" placeholder="Known side effects to watch for..."><?php echo htmlspecialchars($side_effects); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="contraindications">Contraindications</label>
                    <textarea id="contraindications" name="contraindications" placeholder="Conditions or situations where this medicine should not be used..."><?php echo htmlspecialchars($contraindications); ?></textarea>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-warning">
                        <?php echo !empty($medicine_id) ? '💾 Update Medicine' : '💊 Prescribe Medicine'; ?>
                    </button>
                    <a href="medicines.php?admission_id=<?php echo $admission_id; ?>" class="btn btn-secondary">← Back to List</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to admission date
            const admissionDate = '<?php echo $admission['admission_date']; ?>';
            const startDateField = document.getElementById('start_date');
            const endDateField = document.getElementById('end_date');
            startDateField.setAttribute('min', admissionDate);
            
            // Update end date minimum when start date changes
            startDateField.addEventListener('change', function() {
                endDateField.setAttribute('min', this.value);
                if (endDateField.value && endDateField.value < this.value) {
                    endDateField.value = '';
                }
            });
            
            // Set initial minimum for end date
            if (startDateField.value) {
                endDateField.setAttribute('min', startDateField.value);
            }
            
            // Set default date and time
            if (!startDateField.value) {
                startDateField.value = new Date().toISOString().split('T')[0];
            }
            if (!document.getElementById('start_time').value) {
                const now = new Date();
                const timeString = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                document.getElementById('start_time').value = timeString;
            }
        });
    </script>
</body>
</html>