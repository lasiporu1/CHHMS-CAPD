<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Create investigations table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS investigations (
    investigation_id INT AUTO_INCREMENT PRIMARY KEY,
    admission_id INT NOT NULL,
    patient_id INT NULL,
    admission_number VARCHAR(100) NULL,
    investigation_type VARCHAR(100) NOT NULL,
    investigation_name VARCHAR(255) NOT NULL,
    ordered_date DATE NOT NULL,
    ordered_time TIME NULL,
    ordered_by INT NOT NULL,
    result_status VARCHAR(50) DEFAULT 'Pending',
    result_values TEXT NULL,
    urgent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);
// Ensure backward compatibility: add missing columns if table existed without them
$columns_to_add = [
    "patient_id INT NULL",
    "admission_number VARCHAR(100) NULL",
    "ordered_time TIME NULL",
    "result_values TEXT NULL",
    "urgent TINYINT(1) DEFAULT 0",
    "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];
foreach ($columns_to_add as $column) {
    $column_name = explode(' ', $column)[0];
    $check_column = "SHOW COLUMNS FROM investigations LIKE '$column_name'";
    $column_exists = $conn->query($check_column);
    if (!$column_exists || $column_exists->num_rows == 0) {
        $alter_sql = "ALTER TABLE investigations ADD COLUMN $column";
        @$conn->query($alter_sql);
    }
}

// Get admission ID or admission_number from URL (ward or clinic admission)
$is_clinic = false;
$admission_id = null;
if (isset($_GET['admission_id']) && !empty($_GET['admission_id'])) {
    $admission_id = $conn->real_escape_string($_GET['admission_id']);
} elseif (isset($_GET['admission_number']) && !empty($_GET['admission_number'])) {
    // Resolve clinic admission_number to admission_id
    $admission_number = $conn->real_escape_string($_GET['admission_number']);
    $ca_sql = "SELECT ca.*, p.calling_name, p.full_name, p.nic FROM clinic_admissions ca LEFT JOIN patients p ON ca.patient_id = p.patient_id WHERE ca.admission_number = '" . $admission_number . "'";
    $ca_res = $conn->query($ca_sql);
    if ($ca_res && $ca_res->num_rows > 0) {
        $admission = $ca_res->fetch_assoc();
        $admission_id = $admission['admission_id'];
        $is_clinic = true;
    } else {
        header("Location: admission_list.php?error=Admission+not+found");
        exit();
    }
} else {
    header("Location: admission_list.php");
    exit();
}

// If we haven't already fetched the admission row (from clinic lookup), try ward table
if (empty($admission)) {
    $admission_sql = "SELECT wa.*, p.calling_name, p.full_name, p.nic 
                  FROM ward_admissions wa
                  LEFT JOIN patients p ON wa.patient_id = p.patient_id
                  WHERE wa.admission_id = $admission_id";
    $admission_result = $conn->query($admission_sql);
    if ($admission_result && $admission_result->num_rows > 0) {
        $admission = $admission_result->fetch_assoc();
    } else {
        // Try clinic admissions by id as fallback
        $ca_sql = "SELECT ca.*, p.calling_name, p.full_name, p.nic FROM clinic_admissions ca LEFT JOIN patients p ON ca.patient_id = p.patient_id WHERE ca.admission_id = $admission_id";
        $ca_res = $conn->query($ca_sql);
        if ($ca_res && $ca_res->num_rows > 0) {
            $admission = $ca_res->fetch_assoc();
            $is_clinic = true;
        } else {
            header("Location: admission_list.php?error=Admission+not+found");
            exit();
        }
    }
}

// Check if patient is deceased - if yes, redirect with error
if (!empty($admission['patient_id'])) {
    $patient_check_sql = "SELECT patient_status, death_date FROM patients WHERE patient_id = " . (int)$admission['patient_id'];
    $patient_check_result = $conn->query($patient_check_sql);
    if ($patient_check_result && $patient_check_result->num_rows > 0) {
        $patient_status_row = $patient_check_result->fetch_assoc();
        if ($patient_status_row['patient_status'] == 'Deceased') {
            $death_date_display = !empty($patient_status_row['death_date']) ? date('M j, Y', strtotime($patient_status_row['death_date'])) : 'Unknown';
            if ($is_clinic) {
                header("Location: investigations.php?admission_number=" . urlencode($admission['admission_number']) . "&error=" . urlencode("Cannot add/edit investigations. Patient is deceased (Date: " . $death_date_display . ")."));
            } else {
                header("Location: investigations.php?admission_id=" . $admission_id . "&error=" . urlencode("Cannot add/edit investigations. Patient is deceased (Date: " . $death_date_display . ")."));
            }
            exit();
        }
    }
}

// Initialize variables
$investigation_id = $investigation_type = $investigation_name = '';
$ordered_date = $ordered_time = $result_status = $result_values = '';
$urgent = 0;
$error = '';

// Check if editing
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    $where_clause = "i.investigation_id = $id AND (i.admission_id = $admission_id";
    if (!empty($admission['admission_number'])) {
        $where_clause .= " OR i.admission_number = '" . $conn->real_escape_string($admission['admission_number']) . "'";
    }
    $where_clause .= ")";
    $sql = "SELECT * FROM investigations i WHERE " . $where_clause;
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $investigation = $result->fetch_assoc();
        $investigation_id = $investigation['investigation_id'];
        $investigation_type = $investigation['investigation_type'];
        $investigation_name = $investigation['investigation_name'];
        $ordered_date = $investigation['ordered_date'];
        $ordered_time = $investigation['ordered_time'];
        $result_status = $investigation['result_status'];
        $result_values = $investigation['result_values'];
        $urgent = (int)$investigation['urgent'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $investigation_type = $conn->real_escape_string($_POST['investigation_type']);
    $investigation_name = $conn->real_escape_string($_POST['investigation_name']);
    $ordered_date = $conn->real_escape_string($_POST['ordered_date']);
    $ordered_time = !empty($_POST['ordered_time']) ? $conn->real_escape_string($_POST['ordered_time']) : '';
    $result_status = !empty($_POST['result_status']) ? $conn->real_escape_string($_POST['result_status']) : 'Pending';
    $result_values = !empty($_POST['result_values']) ? $conn->real_escape_string($_POST['result_values']) : '';
    $urgent = isset($_POST['urgent']) ? 1 : 0;

    if (empty($investigation_type)) {
        $error = 'Investigation type is required.';
    } elseif (empty($investigation_name)) {
        $error = 'Investigation name is required.';
    } elseif (empty($ordered_date)) {
        $error = 'Ordered date is required.';
    } else {
        // Validate that ordered date is not before admission date
        $admission_date = null;
        if ($is_clinic && !empty($admission['admission_date'])) {
            $admission_date = $admission['admission_date'];
        } elseif (!$is_clinic && !empty($admission['admission_date'])) {
            $admission_date = $admission['admission_date'];
        }
        
        if ($admission_date && strtotime($ordered_date) < strtotime($admission_date)) {
            $error = "Investigation ordered date cannot be earlier than admission date (" . date('M j, Y', strtotime($admission_date)) . ").";
        }
    }

    if (empty($error)) {
        $ordered_by = (int)$_SESSION['user_id'];
        $adnum_val = $is_clinic ? "'" . $conn->real_escape_string($admission['admission_number']) . "'" : 'NULL';
        $pid_val = !empty($admission['patient_id']) ? (int)$admission['patient_id'] : 'NULL';
        $ordered_time_val = !empty($ordered_time) ? "'$ordered_time'" : 'NULL';

        if (!empty($investigation_id)) {
            // Update existing investigation
            $sql = "UPDATE investigations SET 
                    investigation_type='$investigation_type',
                    investigation_name='$investigation_name',
                    ordered_date='$ordered_date',
                    ordered_time=$ordered_time_val,
                    result_status='$result_status',
                    result_values='$result_values',
                    urgent=$urgent
                    WHERE investigation_id=$investigation_id";
        } else {
            // Insert new investigation
            $sql = "INSERT INTO investigations 
                    (admission_id, patient_id, admission_number, investigation_type, investigation_name, 
                     ordered_date, ordered_time, ordered_by, result_status, result_values, urgent) 
                    VALUES 
                    ($admission_id, $pid_val, $adnum_val, '$investigation_type', '$investigation_name',
                     '$ordered_date', $ordered_time_val, $ordered_by, '$result_status', '$result_values', $urgent)";
        }

        if ($conn->query($sql) === TRUE) {
            if ($is_clinic) {
                header('Location: investigations.php?admission_number=' . urlencode($admission['admission_number']));
            } else {
                header('Location: investigations.php?admission_id=' . $admission_id);
            }
            exit();
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
}

?>
<?php include '../../includes/header.php'; ?>

<div class="container">
    <div class="card">
        <div class="header">
            <h2><?php echo !empty($investigation_id) ? 'Edit' : 'Order'; ?> Investigation</h2>
            <div style="font-size:0.95rem;color:#444;margin-top:6px;">
                <strong>Patient:</strong> <?php echo htmlspecialchars($admission['calling_name'] ?: '-') . ' (' . htmlspecialchars($admission['full_name'] ?: '-') . ')'; ?>
                &mdash;
                <strong>Admission:</strong> <?php echo $is_clinic ? htmlspecialchars($admission['admission_number']) : '#' . str_pad((int)$admission_id, 4, '0', STR_PAD_LEFT); ?>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" style="max-width:720px;">
            <div class="form-group">
                <label>Investigation Type <span style="color:red;">*</span></label>
                <select name="investigation_type" class="form-control" required>
                    <option value="">Select type</option>
                    <option value="Laboratory" <?php echo ($investigation_type=='Laboratory') ? 'selected' : ''; ?>>Laboratory</option>
                    <option value="Radiology" <?php echo ($investigation_type=='Radiology') ? 'selected' : ''; ?>>Radiology</option>
                    <option value="Ultrasound" <?php echo ($investigation_type=='Ultrasound') ? 'selected' : ''; ?>>Ultrasound</option>
                    <option value="CT Scan" <?php echo ($investigation_type=='CT Scan') ? 'selected' : ''; ?>>CT Scan</option>
                    <option value="MRI" <?php echo ($investigation_type=='MRI') ? 'selected' : ''; ?>>MRI</option>
                    <option value="ECG" <?php echo ($investigation_type=='ECG') ? 'selected' : ''; ?>>ECG</option>
                    <option value="Echo" <?php echo ($investigation_type=='Echo') ? 'selected' : ''; ?>>Echo</option>
                    <option value="Other" <?php echo ($investigation_type=='Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Investigation Name <span style="color:red;">*</span></label>
                <input type="text" name="investigation_name" class="form-control" value="<?php echo htmlspecialchars($investigation_name); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Ordered Date <span style="color:red;">*</span></label>
                    <input type="date" name="ordered_date" class="form-control" value="<?php echo htmlspecialchars($ordered_date ?: date('Y-m-d')); ?>" required>
                </div>
                <div class="form-group">
                    <label>Ordered Time</label>
                    <input type="time" name="ordered_time" class="form-control" value="<?php echo htmlspecialchars($ordered_time); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Result Status</label>
                <select name="result_status" class="form-control">
                    <option value="Pending" <?php echo ($result_status=='Pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="In Progress" <?php echo ($result_status=='In Progress') ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Completed" <?php echo ($result_status=='Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($result_status=='Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div class="form-group">
                <label>Result Values / Notes</label>
                <textarea name="result_values" class="form-control" rows="4"><?php echo htmlspecialchars($result_values); ?></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="urgent" value="1" <?php echo $urgent ? 'checked' : ''; ?>>
                    Urgent Investigation
                </label>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-warning"><?php echo !empty($investigation_id) ? 'Update' : 'Order'; ?> Investigation</button>
                <?php if ($is_clinic): ?>
                    <a class="btn btn-secondary" href="investigations.php?admission_number=<?php echo urlencode($admission['admission_number']); ?>">Back to list</a>
                <?php else: ?>
                    <a class="btn btn-secondary" href="investigations.php?admission_id=<?php echo htmlspecialchars($admission_id); ?>">Back to list</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
