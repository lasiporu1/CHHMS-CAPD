<?php
include '../../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// AJAX endpoint: return next admission sequence for a given date (CLN-YYYYMMDD-xxxx)
if (isset($_GET['next_adm_seq']) && !empty($_GET['date'])) {
    $date_param = $_GET['date']; // expected YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_param)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'invalid_date']);
        exit();
    }
    $date_str = str_replace('-', '', $date_param); // YYYYMMDD
    $like_pattern = "CLN-" . $date_str . "-%";

    $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(admission_number, '-', -1) AS UNSIGNED)), 0) AS maxseq FROM clinic_admissions WHERE admission_number LIKE ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $like_pattern);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $maxseq = isset($row['maxseq']) ? (int)$row['maxseq'] : 0;
        $next = $maxseq + 1;
        $padded = sprintf('%04d', $next);
        header('Content-Type: application/json');
        echo json_encode(['next' => $padded]);
        exit();
    }
    header('Content-Type: application/json');
    echo json_encode(['error' => 'db_error']);
    exit();
}

// AJAX endpoint: return patient-related defaults (assigned nursing officer, clinic number from patients table)
if (isset($_GET['patient_info']) && !empty($_GET['patient_id'])) {
    $pid = (int)$_GET['patient_id'];
    $out = ['assigned_nursing_officer' => null, 'clinic_number' => null];
    $pstmt = $conn->prepare("SELECT assigned_nursing_officer, clinic_number FROM patients WHERE patient_id = ? LIMIT 1");
    if ($pstmt) {
        $pstmt->bind_param('i', $pid);
        $pstmt->execute();
        $pres = $pstmt->get_result();
        if ($pres && $prow = $pres->fetch_assoc()) {
            $out['assigned_nursing_officer'] = $prow['assigned_nursing_officer'];
            $out['clinic_number'] = $prow['clinic_number'];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit();
}

// Create clinic_admissions table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS clinic_admissions (
    admission_id INT AUTO_INCREMENT PRIMARY KEY,
    admission_number VARCHAR(100) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    admission_date DATETIME NOT NULL,
    weight DECIMAL(6,2) NULL,
    blood_pressure VARCHAR(50) NULL,
    shortness_of_breath TINYINT(1) DEFAULT 0,
    edema TINYINT(1) DEFAULT 0,
    residual_urine_ml INT NULL,
    exchanges INT NULL,
    pd_balance_ml INT NULL,
    balance DECIMAL(8,2) NULL,
    exit_site_status VARCHAR(255) NULL,
    iv_iron VARCHAR(255) NULL,
    erythropoietin VARCHAR(255) NULL,
    capd_solution VARCHAR(20) NULL,
    capd_valve VARCHAR(255) NULL,
    capd_prescription TEXT NULL,
    rrt_plan TINYINT(1) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0
)";
$conn->query($create_table);

// Ensure required columns exist and types are correct (for older DBs)
function _col_exists($conn, $col) {
    $db = defined('DB_NAME') ? DB_NAME : '';
    $sql = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($db) . "' AND TABLE_NAME = 'clinic_admissions' AND COLUMN_NAME = '" . $conn->real_escape_string($col) . "'";
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) return $row['DATA_TYPE'];
    return false;
}

$dt = _col_exists($conn, 'iv_iron');
if ($dt === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN iv_iron VARCHAR(255) NULL");
} elseif (strtolower($dt) !== 'varchar') {
    $conn->query("ALTER TABLE clinic_admissions MODIFY COLUMN iv_iron VARCHAR(255) NULL");
}

$dt = _col_exists($conn, 'erythropoietin');
if ($dt === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN erythropoietin VARCHAR(255) NULL");
} elseif (strtolower($dt) !== 'varchar') {
    $conn->query("ALTER TABLE clinic_admissions MODIFY COLUMN erythropoietin VARCHAR(255) NULL");
}

if (_col_exists($conn, 'capd_solution') === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN capd_solution VARCHAR(20) NULL");
}
if (_col_exists($conn, 'capd_valve') === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN capd_valve VARCHAR(255) NULL");
}

// add next clinic fields if missing
if (_col_exists($conn, 'next_clinic_date') === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN next_clinic_date DATE NULL");
}
if (_col_exists($conn, 'next_clinic_nursing_id') === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN next_clinic_nursing_id INT NULL");
}
if (_col_exists($conn, 'next_clinic_number') === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN next_clinic_number VARCHAR(100) NULL");
}

// Helper: generate admission number
function generateAdmissionNumber($conn) {
    // Generate a per-day sequential number: CLN-YYYYMMDD-XXXX
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM clinic_admissions WHERE DATE(created_at) = ?");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $res = $stmt->get_result();
    $count = 0;
    if ($res && $row = $res->fetch_assoc()) $count = (int)$row['c'];
    $seq = $count + 1;
    return sprintf('CLN-%s-%04d', date('Ymd'), $seq);
}

// Initialize variables
$admission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
admission:
$admission = [
    'admission_number' => '',
    'patient_id' => '',
    'admission_date' => '',
    'next_clinic_date' => '',
    'next_clinic_nursing_id' => '',
    'next_clinic_number' => '',
    'weight' => '',
    'blood_pressure' => '',
    'shortness_of_breath' => 0,
    'edema' => 0,
    'residual_urine_ml' => '',
    'exchanges' => '',
    'pd_balance_ml' => '',
    'balance' => '',
    'exit_site_status' => '',
    'iv_iron' => 0,
    'erythropoietin' => 0,
    'capd_prescription' => '',
    'capd_solution' => '',
    'capd_valve' => '',
    'rrt_plan' => 0
];

if ($admission_id) {
    $stmt = $conn->prepare("SELECT * FROM clinic_admissions WHERE admission_id = ?");
    $stmt->bind_param('i', $admission_id);
    $stmt->execute();
    $res = $stmt->get_result();
        if ($res && $res->num_rows) {
        $admission = $res->fetch_assoc();
        // populate capd_solution/capd_valve from separate columns if present, otherwise attempt to parse legacy capd_prescription
        if (isset($admission['capd_solution']) && $admission['capd_solution'] !== null) {
            $admission['capd_solution'] = $admission['capd_solution'];
            $admission['capd_valve'] = $admission['capd_valve'] ?? '';
        } else {
            if (!empty($admission['capd_prescription']) && strpos($admission['capd_prescription'], '|') !== false) {
                list($sol, $valve) = explode('|', $admission['capd_prescription'], 2);
                $admission['capd_solution'] = $sol;
                $admission['capd_valve'] = $valve;
            } else {
                $admission['capd_solution'] = $admission['capd_prescription'] ?? '';
                $admission['capd_valve'] = '';
            }
        }
    } else {
        $admission_id = 0;
    }
}

// fetch nursing officers for select
$nursing_officers = $conn->query("SELECT nursing_id, nursing_name FROM nursing_officers ORDER BY nursing_name ASC");

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_admission'])) {
    // Gather POST values
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $admission_date = isset($_POST['admission_date']) ? $conn->real_escape_string($_POST['admission_date']) : date('Y-m-d H:i:s');
    $next_clinic_date = isset($_POST['next_clinic_date']) && $_POST['next_clinic_date'] !== '' ? $conn->real_escape_string($_POST['next_clinic_date']) : null;
    $next_clinic_nursing_id = isset($_POST['next_clinic_nursing_id']) && $_POST['next_clinic_nursing_id'] !== '' ? (int)$_POST['next_clinic_nursing_id'] : null;
    $next_clinic_number = isset($_POST['next_clinic_number']) ? $conn->real_escape_string($_POST['next_clinic_number']) : null;
    $weight = $_POST['weight'] !== '' ? (float)$_POST['weight'] : null;
    $blood_pressure = isset($_POST['blood_pressure']) ? $conn->real_escape_string($_POST['blood_pressure']) : null;
    $sob = (isset($_POST['shortness_of_breath']) && $_POST['shortness_of_breath'] === '1') ? 1 : 0;
    $edema = (isset($_POST['edema']) && $_POST['edema'] === '1') ? 1 : 0;
    $residual_urine_ml = $_POST['residual_urine_ml'] !== '' ? (int)$_POST['residual_urine_ml'] : null;
    $exchanges = $_POST['exchanges'] !== '' ? (int)$_POST['exchanges'] : null;
    $pd_balance_ml = $_POST['pd_balance_ml'] !== '' ? (int)$_POST['pd_balance_ml'] : null;
    $balance = $_POST['balance'] !== '' ? (float)$_POST['balance'] : null;
    $exit_site_status = isset($_POST['exit_site_status']) ? $conn->real_escape_string($_POST['exit_site_status']) : null;
    $iv_iron = isset($_POST['iv_iron']) ? $conn->real_escape_string($_POST['iv_iron']) : null;
    $erythropoietin = isset($_POST['erythropoietin']) ? $conn->real_escape_string($_POST['erythropoietin']) : null;
    // Read CAPD solution and valve separately
    $capd_solution = isset($_POST['capd_solution']) ? $conn->real_escape_string($_POST['capd_solution']) : '';
    $capd_valve = isset($_POST['capd_valve']) ? $conn->real_escape_string($_POST['capd_valve']) : '';
    // keep legacy combined field for compatibility
    $capd_prescription = trim($capd_solution . ($capd_valve !== '' ? '|' . $capd_valve : ''));
    $rrt_plan = (isset($_POST['rrt_plan']) && $_POST['rrt_plan'] === '1') ? 1 : 0;

    if (!$patient_id) $errors[] = 'Please select a patient.';

    // prefer posted admission number (generated client-side) if provided
    $posted_adm_number = isset($_POST['admission_number']) ? $conn->real_escape_string($_POST['admission_number']) : '';

    if (empty($errors)) {
            if (!empty($_POST['admission_id'])) {
            // update
            $aid = (int)$_POST['admission_id'];
                $stmt = $conn->prepare("UPDATE clinic_admissions SET patient_id=?, admission_date=?, weight=?, blood_pressure=?, shortness_of_breath=?, edema=?, residual_urine_ml=?, exchanges=?, pd_balance_ml=?, balance=?, exit_site_status=?, iv_iron=?, erythropoietin=?, capd_solution=?, capd_valve=?, capd_prescription=?, rrt_plan=?, next_clinic_date=?, next_clinic_nursing_id=?, next_clinic_number=?, updated_at=NOW() WHERE admission_id=?");
                // use string types for simplicity and to accommodate text fields
                $stmt->bind_param('sssssssssssssssssssss', $patient_id, $admission_date, $weight, $blood_pressure, $sob, $edema, $residual_urine_ml, $exchanges, $pd_balance_ml, $balance, $exit_site_status, $iv_iron, $erythropoietin, $capd_solution, $capd_valve, $capd_prescription, $rrt_plan, $next_clinic_date, $next_clinic_nursing_id, $next_clinic_number, $aid);
            if ($stmt->execute()) {
                $success = 'Admission updated successfully.';
                // log update
                if (function_exists('log_activity')) {
                    log_activity($_SESSION['user_id'] ?? '', 'update', 'clinic_admissions', $aid, json_encode(['patient_id'=>$patient_id]));
                }
                header('Location: clinic_admission_form.php?id=' . $aid . '&saved=1');
                exit();
            } else {
                $errors[] = 'DB Error: ' . $stmt->error;
            }
        } else {
            // insert
            // prevent duplicate admission for same patient on same date
            $admit_date_check = date('Y-m-d', strtotime($admission_date));
            $dupStmt = $conn->prepare("SELECT COUNT(*) AS c FROM clinic_admissions WHERE patient_id = ? AND DATE(admission_date) = ? AND is_deleted = 0");
            if ($dupStmt) {
                $dupStmt->bind_param('is', $patient_id, $admit_date_check);
                $dupStmt->execute();
                $dupRes = $dupStmt->get_result();
                $already = 0;
                if ($dupRes && $row = $dupRes->fetch_assoc()) $already = (int)$row['c'];
                if ($already > 0) {
                    $errors[] = 'This patient already has an admission on ' . $admit_date_check . '. Only one admission per patient per day is allowed.';
                }
            }

            if (empty($errors)) {
                $admission_number = $posted_adm_number !== '' ? $posted_adm_number : generateAdmissionNumber($conn);
                $created_by = $_SESSION['user_id'];
                $stmt = $conn->prepare("INSERT INTO clinic_admissions (admission_number, patient_id, admission_date, weight, blood_pressure, shortness_of_breath, edema, residual_urine_ml, exchanges, pd_balance_ml, balance, exit_site_status, iv_iron, erythropoietin, capd_solution, capd_valve, capd_prescription, rrt_plan, next_clinic_date, next_clinic_nursing_id, next_clinic_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssssssssssssssssssss', $admission_number, $patient_id, $admission_date, $weight, $blood_pressure, $sob, $edema, $residual_urine_ml, $exchanges, $pd_balance_ml, $balance, $exit_site_status, $iv_iron, $erythropoietin, $capd_solution, $capd_valve, $capd_prescription, $rrt_plan, $next_clinic_date, $next_clinic_nursing_id, $next_clinic_number, $created_by);
                if ($stmt->execute()) {
                    $newid = $stmt->insert_id;
                    if (function_exists('log_activity')) {
                        log_activity($_SESSION['user_id'] ?? '', 'create', 'clinic_admissions', $newid, json_encode(['patient_id'=>$patient_id,'admission_number'=>$admission_number]));
                    }
                    header('Location: clinic_admission_form.php?id=' . $newid . '&saved=1');
                    exit();
                } else {
                    $errors[] = 'DB Error: ' . $stmt->error;
                }
            }
            // end insert
        }
    }
}

// Delete action (admin only)
if (isset($_POST['delete_admission']) && !empty($_POST['admission_id'])) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin') {
        $did = (int)$_POST['admission_id'];
        // soft delete
        $stmt = $conn->prepare("UPDATE clinic_admissions SET is_deleted = 1 WHERE admission_id = ?");
        $stmt->bind_param('i', $did);
        if ($stmt->execute()) {
            if (function_exists('log_activity')) {
                log_activity($_SESSION['user_id'] ?? '', 'delete', 'clinic_admissions', $did, null);
            }
            header('Location: clinic_admission_form.php?deleted=1');
            exit();
        } else {
            $errors[] = 'DB Error: ' . $stmt->error;
        }
    } else {
        $errors[] = 'Unauthorized: only Admin can delete.';
    }
}

// Fetch patient display name if patient_id present
$patient_display = '';
if (!empty($admission['patient_id'])) {
    $pstmt = $conn->prepare('SELECT calling_name, full_name FROM patients WHERE patient_id = ?');
    $pstmt->bind_param('i', $admission['patient_id']);
    $pstmt->execute();
    $pres = $pstmt->get_result();
    if ($pres && $pres->num_rows) {
        $prow = $pres->fetch_assoc();
        $patient_display = $prow['calling_name'] . ' (' . $prow['full_name'] . ')';
    }
}

// build nursing officers map for display
$nursing_map = [];
$res_no = $conn->query("SELECT nursing_id, nursing_name FROM nursing_officers ORDER BY nursing_name ASC");
if ($res_no) {
    while ($rno = $res_no->fetch_assoc()) {
        $nursing_map[$rno['nursing_id']] = $rno['nursing_name'];
    }
}
$nursing_display = '';
if (!empty($admission['next_clinic_nursing_id']) && isset($nursing_map[$admission['next_clinic_nursing_id']])) {
    $nursing_display = $nursing_map[$admission['next_clinic_nursing_id']];
}
// Include header (after processing and before output) to avoid header() issues
include '../../includes/header.php';

?>
<div class="container">
    <div class="card">
        <h2>Clinic Admission Form</h2>
        <?php if (!empty($errors)): ?>
            <div style="background:#ffe6e6;border:1px solid #ffcccc;padding:0.8rem;border-radius:6px;margin-bottom:1rem;color:#a00;">
                <?php echo implode('<br>', $errors); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['saved'])): ?>
            <div style="background:#e6ffea;border:1px solid #c8f7d4;padding:0.8rem;border-radius:6px;margin-bottom:1rem;color:#0b6;">Saved successfully.</div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="admission_id" value="<?php echo htmlspecialchars($admission['admission_id'] ?? ''); ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start;">
                <div style="display:flex;gap:1rem;flex-direction:column;">
                    <div style="display:flex;gap:1rem;align-items:center;">
                        <div style="min-width:220px;">
                            <label style="font-weight:600;display:block;margin-bottom:0.4rem;">Admission Date</label>
                            <input type="datetime-local" id="admission_date" name="admission_date" value="<?php echo !empty($admission['admission_date']) ? date('Y-m-d\TH:i', strtotime($admission['admission_date'])) : ''; ?>" style="width:100%;padding:0.6rem;border-radius:6px;border:1px solid #ccc;">
                        </div>

                        <div style="flex:1;">
                            <label style="font-weight:600;display:block;margin-bottom:0.4rem;">Patient</label>
                            <input type="text" id="patient_search" placeholder="Type name, NIC, PHN..." style="width:100%;padding:0.6rem;border-radius:6px;border:1px solid #ccc;" value="<?php echo htmlspecialchars($patient_display); ?>">
                            <input type="hidden" id="patient_id" name="patient_id" value="<?php echo htmlspecialchars($admission['patient_id'] ?? ''); ?>">
                        </div>

                        <div style="min-width:220px;">
                            <label style="font-weight:600;display:block;margin-bottom:0.4rem;">Admission #</label>
                            <input type="text" id="admission_number" name="admission_number" readonly value="<?php echo htmlspecialchars($admission['admission_number']); ?>" style="width:100%;padding:0.6rem;border-radius:6px;border:1px solid #ddd;background:#f5f5f5;">
                        </div>
                    </div>

                    <div style="display:flex;gap:1rem;align-items:center;margin-top:0.75rem;">
                        <div style="min-width:220px;">
                            <label style="font-weight:600;display:block;margin-bottom:0.4rem;">Next Clinic Date</label>
                            <input type="date" id="next_clinic_date" name="next_clinic_date" value="<?php echo htmlspecialchars($admission['next_clinic_date'] ?? ''); ?>" <?php echo empty($admission['patient_id']) ? 'disabled' : ''; ?> style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                        </div>

                        <div style="flex:1;">
                            <label style="font-weight:600;display:block;margin-bottom:0.4rem;">Nursing Officer</label>
                            <div id="next_clinic_nursing_display" style="padding:0.5rem;border-radius:6px;border:1px solid #eee;background:#fafafa;min-height:38px;">
                                <?php echo htmlspecialchars($nursing_display); ?>
                            </div>
                            <input type="hidden" id="next_clinic_nursing_id" name="next_clinic_nursing_id" value="<?php echo htmlspecialchars($admission['next_clinic_nursing_id'] ?? ''); ?>">
                        </div>

                        <div style="min-width:220px;">
                            <label style="font-weight:600;display:block;margin-bottom:0.4rem;">Clinic #</label>
                            <input type="text" id="next_clinic_number" name="next_clinic_number" readonly value="<?php echo htmlspecialchars($admission['next_clinic_number'] ?? ''); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ddd;background:#f5f5f5;">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.75rem;margin-top:0.75rem;">
                        <div>
                            <label style="display:block;margin-bottom:0.4rem;">Weight (kg)</label>
                            <input type="number" step="0.1" name="weight" value="<?php echo htmlspecialchars($admission['weight']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:0.4rem;">Blood Pressure</label>
                            <input type="text" name="blood_pressure" value="<?php echo htmlspecialchars($admission['blood_pressure']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.75rem;margin-top:0.75rem;">
                        <div>
                            <label style="display:block;margin-bottom:0.4rem;">Shortness of Breath</label>
                            <select name="shortness_of_breath" style="width:100%;padding:0.4rem;border-radius:6px;border:1px solid #ccc;">
                                <option value="0" <?php if (empty($admission['shortness_of_breath'])) echo 'selected'; ?>>No</option>
                                <option value="1" <?php if (!empty($admission['shortness_of_breath'])) echo 'selected'; ?>>Yes</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:0.4rem;">Edema</label>
                            <select name="edema" style="width:100%;padding:0.4rem;border-radius:6px;border:1px solid #ccc;">
                                <option value="0" <?php if (empty($admission['edema'])) echo 'selected'; ?>>No</option>
                                <option value="1" <?php if (!empty($admission['edema'])) echo 'selected'; ?>>Yes</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.75rem;margin-top:0.5rem;">
                        <div>
                            <label style="display:block;margin-bottom:0.4rem;">Residual urine (ml/24h)</label>
                            <input type="number" name="residual_urine_ml" value="<?php echo htmlspecialchars($admission['residual_urine_ml']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:0.4rem;">Exchanges</label>
                            <input type="number" name="exchanges" value="<?php echo htmlspecialchars($admission['exchanges']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                        </div>
                    </div>

                </div>

                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="background:#fbfbfb;border:1px solid #eee;padding:0.75rem;border-radius:8px;">
                        <label style="display:block;margin-bottom:0.4rem;font-weight:600;">PD Details</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                            <div>
                                <label style="display:block;margin-bottom:0.3rem;">PD Balance (ml/24hrs)</label>
                                <input type="number" name="pd_balance_ml" value="<?php echo htmlspecialchars($admission['pd_balance_ml']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:0.3rem;">Balance</label>
                                <input type="number" step="0.01" name="balance" value="<?php echo htmlspecialchars($admission['balance']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                            </div>
                        </div>
                        <div style="margin-top:0.5rem;">
                            <label style="display:block;margin-bottom:0.3rem;">Exit site status</label>
                            <input type="text" name="exit_site_status" value="<?php echo htmlspecialchars($admission['exit_site_status']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                        <div>
                            <label style="display:block;margin-bottom:0.4rem;">IV Iron</label>
                            <input type="text" name="iv_iron" value="<?php echo htmlspecialchars($admission['iv_iron']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                        </div>
                        <div>
                            <label style="display:block;margin-bottom:0.4rem;">Erythropoietin</label>
                            <input type="text" name="erythropoietin" value="<?php echo htmlspecialchars($admission['erythropoietin']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                        </div>
                    </div>

                    <div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.5rem;">
                        <div style="min-width:140px;">
                            <label style="display:block;margin-bottom:0.4rem;">CAPD Solution</label>
                            <select name="capd_solution" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                                <option value="" <?php if (empty($admission['capd_solution'])) echo 'selected'; ?>>Select</option>
                                <option value="1.5" <?php if ($admission['capd_solution']==='1.5') echo 'selected'; ?>>1.5</option>
                                <option value="2.5" <?php if ($admission['capd_solution']==='2.5') echo 'selected'; ?>>2.5</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label style="display:block;margin-bottom:0.4rem;">Valve</label>
                            <input type="text" name="capd_valve" placeholder="Enter valve details" value="<?php echo htmlspecialchars($admission['capd_valve']); ?>" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                        </div>
                    </div>

                    <div style="margin-top:0.5rem;">
                        <label style="display:block;margin-bottom:0.4rem;">RRT Plan (KT)</label>
                        <select name="rrt_plan" style="padding:0.4rem;border-radius:6px;border:1px solid #ccc;width:100%;">
                            <option value="0" <?php if (empty($admission['rrt_plan'])) echo 'selected'; ?>>No</option>
                            <option value="1" <?php if (!empty($admission['rrt_plan'])) echo 'selected'; ?>>Yes</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="margin-top:1rem;display:flex;justify-content:space-between;align-items:center;gap:0.5rem;">
                <div style="display:flex;gap:0.5rem;">
                    <button type="submit" name="save_admission" class="btn btn-primary" style="padding:0.6rem 1rem;">Save</button>
                    <?php if (!empty($admission['admission_id'])): ?>
                        <a href="clinic_admission_form.php?id=<?php echo $admission['admission_id']; ?>" class="btn btn-secondary" style="padding:0.6rem 1rem;">Edit</a>
                    <?php endif; ?>
                    <?php if (!empty($admission['admission_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this admission?');">
                            <input type="hidden" name="admission_id" value="<?php echo $admission['admission_id']; ?>">
                            <button type="submit" name="delete_admission" class="btn btn-danger" style="padding:0.6rem 1rem;background:#d9534f;color:#fff;border:none;border-radius:6px;">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div style="display:flex;gap:0.5rem;">
                    <?php if (!empty($admission['admission_id'])): ?>
                        <a href="../../pages/admissions/medicines.php?admission_number=<?php echo urlencode($admission['admission_number']); ?>" class="btn btn-secondary" style="padding:0.6rem 1rem;">Medicine</a>
                        <a href="../../pages/admissions/investigations.php?admission_number=<?php echo urlencode($admission['admission_number']); ?>" class="btn btn-secondary" style="padding:0.6rem 1rem;">Investigation</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

        <script>
        // Ensure clinic number is generated only after a date is selected
        (function(){
            const admDate = document.getElementById('admission_date');
            const admNumber = document.getElementById('admission_number');
            const patientSearch = document.getElementById('patient_search');
            if (!admNumber || !patientSearch) return;

            function setDisabledState(){
                patientSearch.disabled = !admNumber.value;
            }

            async function fetchSeqForDate(dateVal){
                if (!dateVal) return;
                const d = dateVal.split('T')[0];
                if (!d) return;
                try{
                    const res = await fetch('?next_adm_seq=1&date=' + encodeURIComponent(d));
                    const json = await res.json();
                    if (json && json.next){
                        admNumber.value = 'CLN-' + d.replace(/-/g,'') + '-' + json.next;
                        admNumber.style.background = '#fff';
                        setDisabledState();
                        patientSearch.focus();
                    }
                }catch(e){
                    console.error('Failed to fetch admission sequence', e);
                }
            }

            // initial state
            setDisabledState();

            if (admDate){
                admDate.addEventListener('change', function(){
                    // Always regenerate admission number when date changes
                    if (this.value){
                        fetchSeqForDate(this.value);
                    }
                });
            }
        })();

        </script>

        <script>
// Patient search auto-complete reusing patient_detail_search.php
(function(){
            // Dropdown styles
            const style = document.createElement('style');
            style.textContent = `
            .search-results{box-shadow:0 6px 18px rgba(0,0,0,0.08);border-radius:6px;background:#fff;border:1px solid #e6e6e6;max-height:320px;overflow:auto;padding:6px;font-size:14px}
            .search-item{padding:8px;border-radius:6px;display:block;margin-bottom:6px;cursor:pointer}
            .search-item:hover{background:#f1f8ff}
            .search-item .name{font-weight:600;color:#004085}
            .search-item .full{font-weight:400;color:#333;margin-left:6px;font-size:13px}
            .search-item .meta{font-size:12px;color:#666;margin-top:4px}
            `;
            document.head.appendChild(style);

    const input = document.getElementById('patient_search');
    if (!input) return;
    const hidden = document.getElementById('patient_id');
    let dropdown = document.createElement('div');
    dropdown.className = 'search-results';
    dropdown.style.position = 'absolute';
    dropdown.style.zIndex = 1000;
    dropdown.style.display = 'none';
    input.parentNode.style.position = 'relative';
    input.parentNode.appendChild(dropdown);
    let to;
    input.addEventListener('input', function(){
        clearTimeout(to);
        const t = this.value.trim();
        if (!t || t.length < 2) { dropdown.style.display='none'; return; }
        to = setTimeout(()=>{
            fetch('../../pages/reports/patient_detail_search.php?ajax_search=1&term='+encodeURIComponent(t))
                .then(r=>r.json())
                .then(data=>{
                    if (!Array.isArray(data) || data.length==0) { dropdown.innerHTML='<div class="search-item">No patients</div>'; dropdown.style.display='block'; return; }
                    dropdown.innerHTML = data.map(p=>`<div class="search-item" data-id="${p.patient_id}" data-calling="${p.calling_name.replace(/"/g,'&quot;')}" data-full="${p.full_name.replace(/"/g,'&quot;')}" data-nic="${p.nic}" data-phn="${p.hospital_number||''}"><div class="name">${p.calling_name} <span class="full">(${p.full_name})</span></div><div class="meta">NIC: ${p.nic} | PHN: ${p.hospital_number||'-'}</div></div>`).join('');
                    Array.from(dropdown.children).forEach(item=>{
                        item.addEventListener('mousedown', function(e){
                            e.preventDefault();
                            const pid = this.getAttribute('data-id');
                            hidden.value = pid;
                            const calling = this.dataset.calling || '';
                            const full = this.dataset.full || '';
                            input.value = calling + (full ? ' ('+full+')' : '');
                            dropdown.style.display='none';
                            // enable next clinic fields when a patient is selected
                            const nextDate = document.getElementById('next_clinic_date');
                            const nextNurse = document.getElementById('next_clinic_nursing_id');
                            const nextNum = document.getElementById('next_clinic_number');
                            if (nextDate) nextDate.disabled = false;
                            if (nextNum) nextNum.readOnly = true;
                            // fetch patient defaults (nursing officer, clinic number)
                            fetch('?patient_info=1&patient_id=' + encodeURIComponent(pid))
                                .then(r=>r.json())
                                .then(obj=>{
                                    if (obj && obj.assigned_nursing_officer && nextNurse) {
                                        nextNurse.value = obj.assigned_nursing_officer;
                                        const disp = document.getElementById('next_clinic_nursing_display');
                                        if (disp && typeof NURSING_OFFICERS !== 'undefined') {
                                            disp.textContent = NURSING_OFFICERS[obj.assigned_nursing_officer] || '';
                                        }
                                    }
                                    if (obj && obj.clinic_number && nextNum && !nextNum.value) {
                                        nextNum.value = obj.clinic_number;
                                    }
                                }).catch(e=>console.error('patient_info fetch failed',e));
                            if (nextDate) nextDate.focus();
                        });
                    });
                    dropdown.style.display='block';
                }).catch(e=>{ dropdown.innerHTML='<div class="search-item" style="color:#b71c1c;">Error</div>'; dropdown.style.display='block'; });
        },200);
    });
    document.addEventListener('click', function(e){ if (!input.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display='none'; });
})();
</script>

<script>
// Nursing officers map for client-side display
const NURSING_OFFICERS = <?php echo json_encode($nursing_map); ?>;

// next clinic date -> generate clinic number
(function(){
    const nextDate = document.getElementById('next_clinic_date');
    const nextNum = document.getElementById('next_clinic_number');
    if (!nextDate || !nextNum) return;
    nextDate.addEventListener('change', async function(){
        const d = this.value;
        if (!d) return;
        try{
            const res = await fetch('?next_adm_seq=1&date=' + encodeURIComponent(d));
            const json = await res.json();
            if (json && json.next){
                nextNum.value = 'CLN-' + d.replace(/-/g,'') + '-' + json.next;
            }
        }catch(e){ console.error('next clinic seq error', e); }
    });
})();
</script>

<?php include '../../includes/footer.php'; ?>
