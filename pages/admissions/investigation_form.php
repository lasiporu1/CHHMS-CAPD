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

// Create investigations table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS investigations (
    investigation_id INT AUTO_INCREMENT PRIMARY KEY,
    admission_id INT NOT NULL,
    investigation_type VARCHAR(100) NOT NULL,
    investigation_name VARCHAR(255) NOT NULL,
    ordered_date DATE NOT NULL,
    ordered_time TIME NOT NULL,
    ordered_by INT NOT NULL,
    sample_collected TINYINT(1) DEFAULT 0,
    collection_date DATE NULL,
    collection_time TIME NULL,
    result_status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
    result_date DATE NULL,
    result_time TIME NULL,
    result_values TEXT NULL,
    normal_range VARCHAR(255) NULL,
    interpretation TEXT NULL,
    remarks TEXT NULL,
    urgent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Add missing columns to existing investigations table if they don't exist
$columns_to_add = [
    "sample_collected TINYINT(1) DEFAULT 0",
    "collection_date DATE NULL",
    "collection_time TIME NULL",
    "normal_range VARCHAR(255) NULL",
    "interpretation TEXT NULL",
    "remarks TEXT NULL",
    "urgent TINYINT(1) DEFAULT 0"
];

foreach ($columns_to_add as $column) {
    $column_name = explode(' ', $column)[0];
    $check_column = "SHOW COLUMNS FROM investigations LIKE '$column_name'";
    $column_exists = $conn->query($check_column);
    
    if ($column_exists->num_rows == 0) {
        $alter_sql = "ALTER TABLE investigations ADD COLUMN $column";
        $conn->query($alter_sql);
    }
}

// Add result_values column if missing
$check_result_values = "SHOW COLUMNS FROM investigations LIKE 'result_values'";
$result_values_exists = $conn->query($check_result_values);
if ($result_values_exists->num_rows == 0) {
    $add_result_values = "ALTER TABLE investigations ADD COLUMN result_values TEXT NULL";
    $conn->query($add_result_values);
}

// Initialize variables
$investigation_id = $investigation_type = $investigation_name = $ordered_date = $ordered_time = '';
$sample_collected = $collection_date = $collection_time = $result_status = '';
$result_date = $result_time = $result_values = $normal_range = $interpretation = $remarks = '';
$urgent = 0;
$error = '';

// Check if editing
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $sql = "SELECT * FROM investigations WHERE investigation_id = $id AND admission_id = $admission_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $investigation = $result->fetch_assoc();
        $investigation_id = $investigation['investigation_id'];
        $investigation_type = $investigation['investigation_type'];
        $investigation_name = $investigation['investigation_name'];
        $ordered_date = $investigation['ordered_date'];
        $ordered_time = $investigation['ordered_time'];
        $sample_collected = $investigation['sample_collected'];
        $collection_date = $investigation['collection_date'];
        $collection_time = $investigation['collection_time'];
        $result_status = $investigation['result_status'];
        $result_date = $investigation['result_date'];
        $result_time = $investigation['result_time'];
        $result_values = $investigation['result_values'];
        $normal_range = $investigation['normal_range'];
        $interpretation = $investigation['interpretation'];
        $remarks = $investigation['remarks'];
        $urgent = $investigation['urgent'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $investigation_type = $conn->real_escape_string($_POST['investigation_type']);
    $investigation_name = $conn->real_escape_string($_POST['investigation_name']);
    $ordered_date = $conn->real_escape_string($_POST['ordered_date']);
    $ordered_time = $conn->real_escape_string($_POST['ordered_time']);
    $sample_collected = isset($_POST['sample_collected']) ? 1 : 0;
    $collection_date = !empty($_POST['collection_date']) ? $conn->real_escape_string($_POST['collection_date']) : 'NULL';
    $collection_time = !empty($_POST['collection_time']) ? $conn->real_escape_string($_POST['collection_time']) : 'NULL';
    $result_status = $conn->real_escape_string($_POST['result_status']);
    $result_date = !empty($_POST['result_date']) ? $conn->real_escape_string($_POST['result_date']) : 'NULL';
    $result_time = !empty($_POST['result_time']) ? $conn->real_escape_string($_POST['result_time']) : 'NULL';
    $result_values = $conn->real_escape_string($_POST['result_values']);
    $normal_range = $conn->real_escape_string($_POST['normal_range']);
    $interpretation = $conn->real_escape_string($_POST['interpretation']);
    $remarks = $conn->real_escape_string($_POST['remarks']);
    $urgent = isset($_POST['urgent']) ? 1 : 0;
    
    // Validation
    if (empty($investigation_type)) {
        $error = "Investigation type is required!";
    } elseif (empty($investigation_name)) {
        $error = "Investigation name is required!";
    } elseif (empty($ordered_date)) {
        $error = "Ordered date is required!";
    } elseif (empty($ordered_time)) {
        $error = "Ordered time is required!";
    } else {
        // Get admission date for validation
        $admission_check_sql = "SELECT admission_date FROM ward_admissions WHERE admission_id = $admission_id";
        $admission_result_check = $conn->query($admission_check_sql);
        $admission_data = $admission_result_check->fetch_assoc();
        
        // Validate ordered date is not earlier than admission date
        if ($ordered_date < $admission_data['admission_date']) {
            $error = "Investigation ordered date cannot be earlier than admission date (" . date('M j, Y', strtotime($admission_data['admission_date'])) . ").";
        } elseif (!empty($_POST['collection_date']) && $_POST['collection_date'] < $ordered_date) {
            $error = "Sample collection date cannot be earlier than ordered date (" . date('M j, Y', strtotime($ordered_date)) . ").";
        } elseif (!empty($_POST['result_date']) && !empty($_POST['collection_date']) && $_POST['result_date'] < $_POST['collection_date']) {
            $error = "Result date cannot be earlier than sample collection date (" . date('M j, Y', strtotime($_POST['collection_date'])) . ").";
        } else {
        if (!empty($investigation_id)) {
            // Update existing investigation
            $sql = "UPDATE investigations SET 
                    investigation_type = '$investigation_type',
                    investigation_name = '$investigation_name',
                    ordered_date = '$ordered_date',
                    ordered_time = '$ordered_time',
                    sample_collected = $sample_collected,
                    collection_date = " . ($collection_date !== 'NULL' ? "'$collection_date'" : 'NULL') . ",
                    collection_time = " . ($collection_time !== 'NULL' ? "'$collection_time'" : 'NULL') . ",
                    result_status = '$result_status',
                    result_date = " . ($result_date !== 'NULL' ? "'$result_date'" : 'NULL') . ",
                    result_time = " . ($result_time !== 'NULL' ? "'$result_time'" : 'NULL') . ",
                    result_values = '$result_values',
                    normal_range = '$normal_range',
                    interpretation = '$interpretation',
                    remarks = '$remarks',
                    urgent = $urgent
                    WHERE investigation_id = $investigation_id";
        } else {
            // Create new investigation
            $ordered_by = $_SESSION['user_id'];
            $sql = "INSERT INTO investigations 
                    (admission_id, investigation_type, investigation_name, ordered_date, ordered_time, 
                     ordered_by, sample_collected, collection_date, collection_time, result_status, 
                     result_date, result_time, result_values, normal_range, interpretation, remarks, urgent) 
                    VALUES ($admission_id, '$investigation_type', '$investigation_name', '$ordered_date', '$ordered_time', 
                            $ordered_by, $sample_collected, " . 
                            ($collection_date !== 'NULL' ? "'$collection_date'" : 'NULL') . ", " . 
                            ($collection_time !== 'NULL' ? "'$collection_time'" : 'NULL') . ", '$result_status', " . 
                            ($result_date !== 'NULL' ? "'$result_date'" : 'NULL') . ", " . 
                            ($result_time !== 'NULL' ? "'$result_time'" : 'NULL') . ", '$result_values', '$normal_range', '$interpretation', '$remarks', $urgent)";
        }
        
            if ($conn->query($sql) === TRUE) {
                header("Location: investigations.php?admission_id=$admission_id");
                exit();
            } else {
                $error = "Error saving investigation: " . $conn->error;
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
    <title><?php echo !empty($investigation_id) ? 'Edit' : 'Order'; ?> Investigation</title>
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
            border-bottom: 3px solid #2ecc71;
            padding-bottom: 1rem;
        }
        
        .patient-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #2ecc71;
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
            border-color: #2ecc71;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
            background: white;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
            <a href="investigations.php?admission_id=<?php echo $admission_id; ?>">Investigations</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2><?php echo !empty($investigation_id) ? '✏️ Edit' : '🔬 Order'; ?> Investigation</h2>
            
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
                <div class="section-title">🔬 Investigation Details</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="investigation_type">Investigation Type *</label>
                        <select id="investigation_type" name="investigation_type" required>
                            <option value="">Select Type</option>
                            <option value="Blood Test" <?php echo ($investigation_type == 'Blood Test') ? 'selected' : ''; ?>>Blood Test</option>
                            <option value="Urine Test" <?php echo ($investigation_type == 'Urine Test') ? 'selected' : ''; ?>>Urine Test</option>
                            <option value="Imaging" <?php echo ($investigation_type == 'Imaging') ? 'selected' : ''; ?>>Imaging</option>
                            <option value="Microbiology" <?php echo ($investigation_type == 'Microbiology') ? 'selected' : ''; ?>>Microbiology</option>
                            <option value="Biochemistry" <?php echo ($investigation_type == 'Biochemistry') ? 'selected' : ''; ?>>Biochemistry</option>
                            <option value="Hematology" <?php echo ($investigation_type == 'Hematology') ? 'selected' : ''; ?>>Hematology</option>
                            <option value="Dialysis Related" <?php echo ($investigation_type == 'Dialysis Related') ? 'selected' : ''; ?>>Dialysis Related</option>
                            <option value="Other" <?php echo ($investigation_type == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="investigation_name">Investigation Name *</label>
                        <input type="text" id="investigation_name" name="investigation_name" value="<?php echo htmlspecialchars($investigation_name); ?>" required placeholder="e.g., Full Blood Count, Creatinine">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ordered_date">Ordered Date *</label>
                        <input type="date" id="ordered_date" name="ordered_date" value="<?php echo $ordered_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="ordered_time">Ordered Time *</label>
                        <input type="time" id="ordered_time" name="ordered_time" value="<?php echo $ordered_time; ?>" required>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="urgent" name="urgent" value="1" <?php echo $urgent ? 'checked' : ''; ?>>
                    <label for="urgent" style="margin: 0; text-transform: none; color: #e74c3c; font-weight: bold;">🚨 Mark as Urgent</label>
                </div>
                
                <div class="section-title">📋 Sample Collection</div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="sample_collected" name="sample_collected" value="1" <?php echo $sample_collected ? 'checked' : ''; ?>>
                    <label for="sample_collected" style="margin: 0; text-transform: none;">✓ Sample Collected</label>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="collection_date">Collection Date</label>
                        <input type="date" id="collection_date" name="collection_date" value="<?php echo $collection_date !== 'NULL' ? $collection_date : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="collection_time">Collection Time</label>
                        <input type="time" id="collection_time" name="collection_time" value="<?php echo $collection_time !== 'NULL' ? $collection_time : ''; ?>">
                    </div>
                </div>
                
                <div class="section-title">📊 Results</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="result_status">Result Status</label>
                        <select id="result_status" name="result_status">
                            <option value="Pending" <?php echo ($result_status == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo ($result_status == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo ($result_status == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo ($result_status == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="normal_range">Normal Range</label>
                        <input type="text" id="normal_range" name="normal_range" value="<?php echo htmlspecialchars($normal_range); ?>" placeholder="e.g., 12-15 g/dL">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="result_date">Result Date</label>
                        <input type="date" id="result_date" name="result_date" value="<?php echo $result_date !== 'NULL' ? $result_date : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="result_time">Result Time</label>
                        <input type="time" id="result_time" name="result_time" value="<?php echo $result_time !== 'NULL' ? $result_time : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="result_values">Result Values</label>
                    <textarea id="result_values" name="result_values" placeholder="Enter the investigation results..."><?php echo htmlspecialchars($result_values); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="interpretation">Interpretation</label>
                    <textarea id="interpretation" name="interpretation" placeholder="Clinical interpretation of results..."><?php echo htmlspecialchars($interpretation); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" placeholder="Additional notes or comments..."><?php echo htmlspecialchars($remarks); ?></textarea>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-success">
                        <?php echo !empty($investigation_id) ? '💾 Update Investigation' : '🔬 Order Investigation'; ?>
                    </button>
                    <a href="investigations.php?admission_id=<?php echo $admission_id; ?>" class="btn btn-secondary">← Back to List</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to admission date
            const admissionDate = '<?php echo $admission['admission_date']; ?>';
            const orderedDateField = document.getElementById('ordered_date');
            const collectionDateField = document.getElementById('collection_date');
            orderedDateField.setAttribute('min', admissionDate);
            
            // Update collection date minimum when ordered date changes
            orderedDateField.addEventListener('change', function() {
                collectionDateField.setAttribute('min', this.value);
                if (collectionDateField.value && collectionDateField.value < this.value) {
                    collectionDateField.value = '';
                }
            });
            
            // Update result date minimum when collection date changes
            const resultDateField = document.getElementById('result_date');
            collectionDateField.addEventListener('change', function() {
                if (this.value) {
                    resultDateField.setAttribute('min', this.value);
                    if (resultDateField.value && resultDateField.value < this.value) {
                        resultDateField.value = '';
                    }
                }
            });
            
            // Set initial minimums
            if (orderedDateField.value) {
                collectionDateField.setAttribute('min', orderedDateField.value);
            }
            if (collectionDateField.value) {
                resultDateField.setAttribute('min', collectionDateField.value);
            }
            
            // Set default date and time
            if (!orderedDateField.value) {
                orderedDateField.value = new Date().toISOString().split('T')[0];
            }
            if (!document.getElementById('ordered_time').value) {
                const now = new Date();
                const timeString = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                document.getElementById('ordered_time').value = timeString;
            }
        });
    </script>
</body>
</html>