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
    patient_id INT NULL,
    admission_number VARCHAR(100) NULL,
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
    status ENUM('Active', 'Omit', 'On Hold', 'Complete', 'Discontinued', 'Completed') DEFAULT 'Active',
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

// allow storing patient_id on medicines for reporting
$columns_to_add[] = "patient_id INT NULL";

// allow storing clinic admission number on medicines
$columns_to_add[] = "admission_number VARCHAR(100) NULL";

// allow storing master id for medicines
$columns_to_add[] = "medicine_master_id INT NULL";

foreach ($columns_to_add as $column) {
    $column_name = explode(' ', $column)[0];
    $check_column = "SHOW COLUMNS FROM medicines LIKE '$column_name'";
    $column_exists = $conn->query($check_column);
    
    if ($column_exists->num_rows == 0) {
        $alter_sql = "ALTER TABLE medicines ADD COLUMN $column";
        $conn->query($alter_sql);
    }
}

// Ensure status enum includes the new values (attempt to modify existing table)
$alter_status_sql = "ALTER TABLE medicines MODIFY status ENUM('Active','Omit','On Hold','Complete','Discontinued','Completed') DEFAULT 'Active'";
@$conn->query($alter_status_sql);

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
    // Try to recover admission context from HTTP_REFERER (best-effort)
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $ref = $_SERVER['HTTP_REFERER'];
        $parts = parse_url($ref);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
            if (!empty($qs['admission_number'])) {
                $admission_number = $conn->real_escape_string($qs['admission_number']);
                $ca_sql = "SELECT ca.*, p.calling_name, p.full_name, p.nic FROM clinic_admissions ca LEFT JOIN patients p ON ca.patient_id = p.patient_id WHERE ca.admission_number = '" . $admission_number . "'";
                $ca_res = $conn->query($ca_sql);
                if ($ca_res && $ca_res->num_rows > 0) {
                    $admission = $ca_res->fetch_assoc();
                    $admission_id = $admission['admission_id'];
                    $is_clinic = true;
                }
            } elseif (!empty($qs['admission_id'])) {
                $admission_id = $conn->real_escape_string($qs['admission_id']);
            }
        }
    }
    if (empty($admission_id)) {
        header("Location: admission_list.php");
        exit();
    }
}

// Additional fallback: resolve by patient_id if provided and admission still missing
if (empty($admission) && !empty($_GET['patient_id'])) {
    $patient_id = $conn->real_escape_string($_GET['patient_id']);
    // Try to find latest clinic admission for this patient
    $ca_sql = "SELECT ca.*, p.calling_name, p.full_name, p.nic FROM clinic_admissions ca LEFT JOIN patients p ON ca.patient_id = p.patient_id WHERE ca.patient_id = $patient_id ORDER BY ca.admission_date DESC LIMIT 1";
    $ca_res = $conn->query($ca_sql);
    if ($ca_res && $ca_res->num_rows > 0) {
        $admission = $ca_res->fetch_assoc();
        $admission_id = $admission['admission_id'];
        $is_clinic = true;
    }
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
                header("Location: medicines.php?admission_number=" . urlencode($admission['admission_number']) . "&error=" . urlencode("Cannot add/edit medicines. Patient is deceased (Date: " . $death_date_display . ")."));
            } else {
                header("Location: medicines.php?admission_id=" . $admission_id . "&error=" . urlencode("Cannot add/edit medicines. Patient is deceased (Date: " . $death_date_display . ")."));
            }
            exit();
        }
    }
}

// Initialize variables (only keep fields used by the simplified form)
$medicine_id = $medicine_name = $dosage = $route = $frequency = '';
$start_date = $end_date = $indication = '';
$status = 'Active';
$error = '';

// load medicine master list for dropdown autofill
$masters = $conn->query("SELECT * FROM medicine_master WHERE active = 1 ORDER BY medicine_name ASC");

// Check if editing
if (isset($_GET['edit'])) {
    $id = $conn->real_escape_string($_GET['edit']);
    // allow editing medicines linked by admission_id or clinic admission_number
    $where_clause = "m.medicine_id = $id AND (m.admission_id = $admission_id";
    if (!empty($admission['admission_number'])) {
        $where_clause .= " OR m.admission_number = '" . $conn->real_escape_string($admission['admission_number']) . "'";
    }
    $where_clause .= ")";
    $sql = "SELECT * FROM medicines m WHERE " . $where_clause;
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $medicine = $result->fetch_assoc();
        $medicine_id = $medicine['medicine_id'];
        $medicine_name = $medicine['medicine_name'];
        $dosage = $medicine['dosage'];
        $route = $medicine['route'];
        $frequency = $medicine['frequency'];
        $start_date = $medicine['start_date'];
        $end_date = $medicine['end_date'];
        $indication = $medicine['indication'];
        $status = $medicine['status'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $master_id = isset($_POST['master_id']) ? (int)$_POST['master_id'] : 0;
    $medicine_name = '';
    // If a master was selected, prefer canonical name from medicine_master
    if ($master_id > 0) {
        $mres = $conn->query("SELECT medicine_name FROM medicine_master WHERE master_id = $master_id LIMIT 1");
        if ($mres && $mres->num_rows > 0) {
            $medicine_name = $conn->real_escape_string($mres->fetch_assoc()['medicine_name']);
        }
    }
    // Fallback (shouldn't be used since manual entry removed) — accept posted medicine_name if present
    if (empty($medicine_name) && isset($_POST['medicine_name'])) {
        $medicine_name = $conn->real_escape_string($_POST['medicine_name']);
    }
    $dosage = $conn->real_escape_string($_POST['dosage']);
    $route = $conn->real_escape_string($_POST['route']);
    $frequency = $conn->real_escape_string($_POST['frequency']);
    $start_date = $conn->real_escape_string($_POST['start_date']);
    // We reuse the existing 'indication' DB column to store a short remark
    $indication = isset($_POST['indication']) ? $conn->real_escape_string($_POST['indication']) : '';
    $status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : 'Active';
    // If status is Active we do not store an end date
    if ($status === 'Active') {
        $end_date = 'NULL';
    } else {
        $end_date = !empty($_POST['end_date']) ? $conn->real_escape_string($_POST['end_date']) : 'NULL';
    }

    // Validation: require selecting from master list
    if (empty($master_id)) {
        $error = "Please select a medicine from the master list.";
    } elseif (empty($dosage)) {
        $error = "Dosage is required!";
    } elseif (empty($route)) {
        $error = "Route is required!";
    } elseif (empty($frequency)) {
        $error = "Frequency is required!";
    } elseif (empty($start_date)) {
        $error = "Start date is required!";
    } else {
        // Validate that start date is not before admission date
        $admission_date = null;
        if ($is_clinic && !empty($admission['admission_date'])) {
            $admission_date = $admission['admission_date'];
        } elseif (!$is_clinic && !empty($admission['admission_date'])) {
            $admission_date = $admission['admission_date'];
        }
        
        if ($admission_date && strtotime($start_date) < strtotime($admission_date)) {
            $error = "Medicine start date cannot be earlier than admission date (" . date('M j, Y', strtotime($admission_date)) . ").";
        }
        
        // Validate start/end dates
        $start_ts = !empty($start_date) ? strtotime($start_date) : false;
        if ($start_ts === false) {
            $error = "Invalid start date.";
        } else {
            $start_day = date('Y-m-d', $start_ts);
            if ($end_date !== 'NULL') {
                $end_ts = strtotime($end_date);
                if ($end_ts === false) {
                    $error = "Invalid end date.";
                } elseif (date('Y-m-d', $end_ts) < $start_day) {
                    $error = "Schedule end date cannot be earlier than schedule start date (" . date('M j, Y', $start_ts) . ").";
                }
            }
        }

        // ensure or create medicine_card for this patient (link medicines)
        $medicine_card_id = 'NULL';
        if (!empty($admission['patient_id'])) {
            $pid = (int)$admission['patient_id'];
            $cr = $conn->query("SELECT medicine_card_id FROM medicine_cards WHERE patient_id = $pid ORDER BY created_at LIMIT 1");
            if ($cr && $cr->num_rows) {
                $medicine_card_id = (int)$cr->fetch_assoc()['medicine_card_id'];
            } else {
                $conn->query("INSERT INTO medicine_cards (patient_id) VALUES ($pid)");
                $medicine_card_id = $conn->insert_id;
            }
        }

        // proceed to save when validation passed
        if (empty($error)) {
            // server-side fallback: if master_id not provided but medicine_name matches a master, resolve it
            if (empty($master_id) && !empty($medicine_name)) {
                $mres = $conn->query("SELECT master_id FROM medicine_master WHERE LOWER(medicine_name) = LOWER('" . $conn->real_escape_string($medicine_name) . "') LIMIT 1");
                if ($mres && $mres->num_rows > 0) {
                    $master_id = (int)$mres->fetch_assoc()['master_id'];
                }
            }
            // Prevent adding duplicate medicine for the same patient across any admission
            // Rule: block if there exists a record for the same patient with the same medicine
            // AND (status = 'Active' OR end_date IS NULL/empty). Allow new assignment only when
            // previous record is not Active and has a non-empty end_date.
            // Strict check: if adding new and status Active and master_id available, block when
            // a medicines row exists with same patient_id AND same medicine_master_id with status = 'Active'
            if (empty($medicine_id)) {
                // universal duplicate protection before insert: check patient + master OR patient + normalized name
                $pid_check = !empty($admission['patient_id']) ? (int)$admission['patient_id'] : 0;
                if ($pid_check) {
                    $conds = [];
                    if (!empty($master_id) && $master_id > 0) {
                        $conds[] = "medicine_master_id = " . (int)$master_id;
                    }
                    $name_check = $conn->real_escape_string(trim($medicine_name));
                    if ($name_check !== '') {
                        $conds[] = "TRIM(LOWER(medicine_name)) = TRIM(LOWER('" . $name_check . "'))";
                    }
                    if (!empty($conds)) {
                        $dup_where = implode(' OR ', $conds);
                        $dup_sql = "SELECT medicine_id, admission_id, admission_number, start_date, status FROM medicines WHERE patient_id = $pid_check AND status = 'Active' AND (" . $dup_where . ") LIMIT 1";
                        $dup_res = $conn->query($dup_sql);
                        if ($dup_res && $dup_res->num_rows > 0) {
                            $r = $dup_res->fetch_assoc();
                            $started = $r['start_date'] ? date('M j, Y', strtotime($r['start_date'])) : $r['start_date'];
                            $conflict_link = '';
                            if (!empty($r['admission_number'])) {
                                $conflict_link = 'medicine_form.php?admission_number=' . urlencode($r['admission_number']) . '&edit=' . (int)$r['medicine_id'];
                            } elseif (!empty($r['admission_id'])) {
                                $conflict_link = 'medicine_form.php?admission_id=' . (int)$r['admission_id'] . '&edit=' . (int)$r['medicine_id'];
                            } else {
                                $conflict_link = '../medicines/medicine_card.php?patient_id=' . $pid_check;
                            }
                            $status_disp = htmlspecialchars($r['status']);
                            $link_html = '<a href="' . htmlspecialchars($conflict_link) . '">View conflicting prescription</a>';
                            $error = "A conflicting prescription already exists for this patient (status: " . $status_disp . ", started on " . $started . "). " . $link_html . "";
                        }
                    }
                }
            }
            
            // Only proceed with save if no duplicate error was found
            if (empty($error)) {
                if (!empty($medicine_id)) {
                    // Update existing medicine (only the simplified fields)
                        $sql = "UPDATE medicines SET 
                                medicine_name = '$medicine_name',
                                dosage = '$dosage',
                                route = '$route',
                                frequency = '$frequency',
                                start_date = '$start_date',
                                end_date = " . ($end_date !== 'NULL' ? "'$end_date'" : 'NULL') . ",
                                indication = '$indication',
                                status = '$status',
                                medicine_master_id = " . ($master_id > 0 ? $master_id : 'NULL') . ",
                                medicine_card_id = " . ($medicine_card_id !== 'NULL' ? $medicine_card_id : 'NULL') . ",
                                patient_id = " . (!empty($admission['patient_id']) ? (int)$admission['patient_id'] : 'NULL') . ",
                                admission_number = " . ($is_clinic ? "'" . $conn->real_escape_string($admission['admission_number']) . "'" : "NULL") . "
                            WHERE medicine_id = $medicine_id";
                } else {
                    // Create new medicine (only the simplified fields)
                    $prescribed_by = $_SESSION['user_id'];
                    $adnum_val = $is_clinic ? "'" . $conn->real_escape_string($admission['admission_number']) . "'" : "NULL";
                        $sql = "INSERT INTO medicines 
                            (admission_id, patient_id, admission_number, medicine_card_id, medicine_master_id, medicine_name, dosage, route, frequency, 
                             start_date, end_date, prescribed_by, indication, status) 
                            VALUES ($admission_id, " . (!empty($admission['patient_id']) ? (int)$admission['patient_id'] : 'NULL') . ", $adnum_val, " . ($medicine_card_id !== 'NULL' ? $medicine_card_id : 'NULL') . ", " . ($master_id > 0 ? $master_id : 'NULL') . ", '$medicine_name', '$dosage', '$route', '$frequency', 
                                '$start_date', " . ($end_date !== 'NULL' ? "'$end_date'" : 'NULL') . ", $prescribed_by, '$indication', '$status')";
                }

                    if ($conn->query($sql) === TRUE) {
                    if ($is_clinic) {
                        header("Location: medicines.php?admission_number=" . urlencode($admission['admission_number']));
                    } else {
                        header("Location: medicines.php?admission_id=$admission_id");
                    }
                    exit();
                } else {
                    $error = "Error saving medicine: " . $conn->error;
                }
            }
        }
    }
}

// Ensure $admission is present for ward or clinic admissions
if (!$is_clinic) {
    $admission_sql = "SELECT wa.*, p.calling_name, p.full_name, p.nic 
                  FROM ward_admissions wa
                  LEFT JOIN patients p ON wa.patient_id = p.patient_id
                  WHERE wa.admission_id = $admission_id";
    $admission_result = $conn->query($admission_sql);
    if (!$admission_result || $admission_result->num_rows == 0) {
        // Admission not found — redirect back to list
        header("Location: admission_list.php?error=Admission+not+found");
        exit();
    }
    $admission = $admission_result->fetch_assoc();
} else {
    // clinic admission already resolved earlier
    $admission = $admission; // keep existing
}
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
            <a href="medicines.php?admission_id=<?php echo $admission_id; ?><?php if ($is_clinic) echo '&admission_number='.urlencode($admission['admission_number']); ?>">Medicines</a>
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
                <strong>Admission ID:</strong> #<?php echo str_pad($admission_id, 4, '0', STR_PAD_LEFT); ?><br>
                <strong>Type:</strong>
                <?php if ($is_clinic): ?>
                    Clinic Admission &mdash; <strong>Admission No:</strong> <?php echo htmlspecialchars($admission['admission_number'] ?? '-'); ?>
                <?php else: ?>
                    Ward Admission &mdash; <strong>Ward/Bed:</strong> <?php echo htmlspecialchars($admission['ward_bed'] ?? '-'); ?>
                <?php endif; ?>
                <br>
                <strong>Admission Date:</strong>
                <?php echo !empty($admission['admission_date']) ? date('d/m/Y', strtotime($admission['admission_date'])) : '-'; ?>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="section-title">💊 Medicine Details</div>

                <div class="form-row">
                    <div class="form-group" style="grid-column:1/3">
                        <label for="master_select">Select from Master (optional)</label>
                        <select id="master_select" style="width:100%;padding:8px;margin-bottom:8px">
                            <option value="">-- Choose from master list --</option>
                                <?php if ($masters && $masters->num_rows) {
                                    // rewind result if necessary
                                    $masters->data_seek(0);
                                    while ($mm = $masters->fetch_assoc()) {
                                        $dname = htmlspecialchars($mm['medicine_name']);
                                        $dg = htmlspecialchars($mm['generic_name']);
                                        $str = htmlspecialchars($mm['strength']);
                                        $dd = htmlspecialchars($mm['default_dosage']);
                                        $dr = htmlspecialchars($mm['default_route']);
                                        $df = htmlspecialchars($mm['default_frequency']);
                                        $sel = '';
                                        if (!empty($medicine_name) && strtolower($mm['medicine_name']) === strtolower($medicine_name)) {
                                            $sel = ' selected';
                                        }
                                        echo '<option value="' . $mm['master_id'] . '"' . $sel . ' data-name="' . $dname . '" data-generic="' . $dg . '" data-strength="' . $str . '" data-dosage="' . $dd . '" data-route="' . $dr . '" data-frequency="' . $df . '">' . $dname . ($dg? ' (' . $dg . ')':'') . '</option>';
                                    }
                                } ?>
                            </select>
                            <input type="hidden" id="medicine_name" name="medicine_name" value="<?php echo htmlspecialchars($medicine_name); ?>">
                            <input type="hidden" id="master_id" name="master_id" value="<?php echo isset($master_id) ? (int)$master_id : ''; ?>">
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
                            <option value="IP" <?php echo ($route == 'IP') ? 'selected' : ''; ?>>Intraperitoneal (IP)</option>
                            <option value="ID" <?php echo ($route == 'ID') ? 'selected' : ''; ?>>Intradermal (ID)</option>
                            <option value="LA" <?php echo ($route == 'LA') ? 'selected' : ''; ?>>Local (LA)</option>
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
                            <option value="Daily" <?php echo ($frequency == 'Daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="Nocte" <?php echo ($frequency == 'Nocte') ? 'selected' : ''; ?>>Nocte</option>
                            <option value="Mane" <?php echo ($frequency == 'Mane') ? 'selected' : ''; ?>>Mane</option>
                            <option value="BD" <?php echo ($frequency == 'BD') ? 'selected' : ''; ?>>BD</option>
                            <option value="TDS" <?php echo ($frequency == 'TDS') ? 'selected' : ''; ?>>TDS</option>
                            <option value="6 Hrly" <?php echo ($frequency == '6 Hrly') ? 'selected' : ''; ?>>6 Hrly</option>
                            <option value="8 Hrly" <?php echo ($frequency == '8 Hrly') ? 'selected' : ''; ?>>8 Hrly</option>
                            <option value="EDO" <?php echo ($frequency == 'EDO') ? 'selected' : ''; ?>>EDO</option>
                            <option value="Once a Week" <?php echo ($frequency == 'Once a Week') ? 'selected' : ''; ?>>Once a Week</option>
                            <option value="SOS" <?php echo ($frequency == 'SOS') ? 'selected' : ''; ?>>SOS</option>
                            <option value="Stat" <?php echo ($frequency == 'Stat') ? 'selected' : ''; ?>>Stat</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="Active" <?php echo ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Omit" <?php echo ($status == 'Omit') ? 'selected' : ''; ?>>Omit</option>
                            <option value="On Hold" <?php echo ($status == 'On Hold') ? 'selected' : ''; ?>>On Hold</option>
                            <option value="Complete" <?php echo ($status == 'Complete') ? 'selected' : ''; ?>>Complete</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="form-group" id="end_date_group">
                        <label for="end_date">End Date (Optional)</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date !== 'NULL' ? $end_date : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="grid-column:1/3">
                        <label for="indication">Remark</label>
                        <input type="text" id="indication" name="indication" value="<?php echo htmlspecialchars($indication); ?>" placeholder="Optional remark">
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-warning">
                        <?php echo !empty($medicine_id) ? '💾 Update Medicine' : '💊 Prescribe Medicine'; ?>
                    </button>
                    <a href="medicines.php?admission_id=<?php echo $admission_id; ?><?php if ($is_clinic) echo '&admission_number='.urlencode($admission['admission_number']); ?>" class="btn btn-secondary">← Back to List</a>
                </div>
            </form>
            
            <!-- Medicines grid: show simplified columns below the form -->
            <div style="margin-top:2rem;">
                <h3 style="margin-bottom:1rem;">Prescribed Medicines</h3>
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f1f1f1;text-align:left;">
                            <th style="padding:8px;border:1px solid #e6e6e6;">Medicine</th>
                            <th style="padding:8px;border:1px solid #e6e6e6;">Dosage</th>
                            <th style="padding:8px;border:1px solid #e6e6e6;">Frequency</th>
                            <th style="padding:8px;border:1px solid #e6e6e6;">Route</th>
                            <th style="padding:8px;border:1px solid #e6e6e6;">Start</th>
                            <th style="padding:8px;border:1px solid #e6e6e6;">End</th>
                            <th style="padding:8px;border:1px solid #e6e6e6;">Status</th>
                            <th style="padding:8px;border:1px solid #e6e6e6;">Remark</th>
                            <th style="padding:8px;border:1px solid #e6e6e6;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $where_clause = "m.admission_id = $admission_id";
                        if ($is_clinic && !empty($admission['admission_number'])) {
                            $where_clause = "(m.admission_id = $admission_id OR m.admission_number = '" . $conn->real_escape_string($admission['admission_number']) . "')";
                        }
                        $list_sql = "SELECT m.* FROM medicines m WHERE " . $where_clause . " ORDER BY m.created_at DESC";
                        $res = $conn->query($list_sql);
                        if ($res && $res->num_rows > 0) {
                            while ($row = $res->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($row['medicine_name']) . '</td>';
                                echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($row['dosage']) . '</td>';
                                echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($row['frequency']) . '</td>';
                                echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($row['route']) . '</td>';
                                echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($row['start_date']) . '</td>';
                                echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . ($row['end_date'] ? htmlspecialchars($row['end_date']) : '') . '</td>';
                                echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($row['status']) . '</td>';
                                echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($row['indication']) . '</td>';
                                $edit_link = 'medicine_form.php?admission_id=' . $admission_id . '&edit=' . $row['medicine_id'];
                                if ($is_clinic) $edit_link = 'medicine_form.php?admission_number=' . urlencode($admission['admission_number']) . '&edit=' . $row['medicine_id'];
                                echo '<td style="padding:8px;border:1px solid #e6e6e6;"><a href="' . $edit_link . '">Edit</a></td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="9" style="padding:12px;text-align:center;color:#666;">No medicines prescribed yet.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to admission date
            const admissionDate = '<?php echo $admission['admission_date']; ?>';
            const startDateField = document.getElementById('start_date');
            // Focus medicine name for quick entry
            const medName = document.getElementById('medicine_name');
            if (medName) medName.focus();
            const endDateField = document.getElementById('end_date');
            startDateField.setAttribute('min', admissionDate);

            // Master list autofill: when a master is selected, populate fields
            const masterSelect = document.getElementById('master_select');
            if (masterSelect) {
                masterSelect.addEventListener('change', function() {
                    const opt = this.options[this.selectedIndex];
                    if (!opt) return;
                    const name = opt.getAttribute('data-name') || '';
                    const dosage = opt.getAttribute('data-dosage') || '';
                    const route = opt.getAttribute('data-route') || '';
                    const frequency = opt.getAttribute('data-frequency') || '';
                    const mid = this.value || '';

                    // populate display and hidden fields
                    const disp = document.getElementById('medicine_display');
                    const hiddenName = document.getElementById('medicine_name');
                    const hiddenMid = document.getElementById('master_id');
                    if (disp) disp.value = name;
                    if (hiddenName) hiddenName.value = name;
                    if (hiddenMid) hiddenMid.value = mid;

                    if (dosage) document.getElementById('dosage').value = dosage;
                    if (frequency) {
                        const freqEl = document.getElementById('frequency');
                        for (let i=0;i<freqEl.options.length;i++) if (freqEl.options[i].value == frequency) { freqEl.selectedIndex = i; break; }
                    }
                    if (route) {
                        const routeEl = document.getElementById('route');
                        for (let i=0;i<routeEl.options.length;i++) if (routeEl.options[i].value == route) { routeEl.selectedIndex = i; break; }
                    }
                });
                // ensure hidden fields are populated on initial load if a master is already selected
                try { masterSelect.dispatchEvent(new Event('change')); } catch(e) {
                    // fallback for older browsers
                    var ev = document.createEvent('HTMLEvents'); ev.initEvent('change', true, false); masterSelect.dispatchEvent(ev);
                }
            }

            // Update end date minimum when start date changes
            startDateField.addEventListener('change', function() {
                endDateField.setAttribute('min', this.value);
                if (endDateField.value && endDateField.value < this.value) {
                    endDateField.value = '';
                }
            });

            // Hide/show end date when status is Active
            const statusSel = document.getElementById('status');
            const endGroup = document.getElementById('end_date_group');
            function toggleEndGroup() {
                if (!endGroup || !statusSel) return;
                if (statusSel.value === 'Active') {
                    endGroup.style.display = 'none';
                    if (endDateField) endDateField.value = '';
                } else {
                    endGroup.style.display = '';
                }
            }
            if (statusSel) {
                statusSel.addEventListener('change', toggleEndGroup);
                toggleEndGroup();
            }

            // Set initial minimum for end date
            if (startDateField.value) {
                endDateField.setAttribute('min', startDateField.value);
            }

            // Set default date
            if (!startDateField.value) {
                startDateField.value = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>