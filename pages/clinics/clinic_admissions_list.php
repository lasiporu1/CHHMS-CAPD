<?php
include '../../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
include '../../includes/header.php';

// Ensure clinic_admissions table exists (create if missing)
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

// Ensure required columns exist (handle older DBs)
function _col_exists_list($conn, $col) {
    $db = defined('DB_NAME') ? DB_NAME : '';
    $sql = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($db) . "' AND TABLE_NAME = 'clinic_admissions' AND COLUMN_NAME = '" . $conn->real_escape_string($col) . "'";
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) return $row['DATA_TYPE'];
    return false;
}

$dt = _col_exists_list($conn, 'iv_iron');
if ($dt === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN iv_iron VARCHAR(255) NULL");
} elseif (strtolower($dt) !== 'varchar') {
    $conn->query("ALTER TABLE clinic_admissions MODIFY COLUMN iv_iron VARCHAR(255) NULL");
}

$dt = _col_exists_list($conn, 'erythropoietin');
if ($dt === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN erythropoietin VARCHAR(255) NULL");
} elseif (strtolower($dt) !== 'varchar') {
    $conn->query("ALTER TABLE clinic_admissions MODIFY COLUMN erythropoietin VARCHAR(255) NULL");
}

if (_col_exists_list($conn, 'capd_solution') === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN capd_solution VARCHAR(20) NULL");
}
if (_col_exists_list($conn, 'capd_valve') === false) {
    $conn->query("ALTER TABLE clinic_admissions ADD COLUMN capd_valve VARCHAR(255) NULL");
}

// Load nursing officers for filter
$nursing_officers = $conn->query("SELECT nursing_id, nursing_name FROM nursing_officers ORDER BY nursing_name ASC");

// Build filters from GET
$filter_nursing = isset($_GET['filter_nursing_officer']) ? trim($_GET['filter_nursing_officer']) : '';
$filter_date = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

// Fetch non-deleted admissions with optional filters
$where = ["ca.is_deleted = 0"];
if ($filter_nursing !== '') {
    $filter_nursing_esc = $conn->real_escape_string($filter_nursing);
    $where[] = "ca.next_clinic_nursing_id = '" . $filter_nursing_esc . "'";
}
if ($filter_date !== '') {
    // expect YYYY-MM-DD
    $filter_date_esc = $conn->real_escape_string($filter_date);
    $where[] = "DATE(ca.admission_date) = '" . $filter_date_esc . "'";
}
$sql = "SELECT ca.*, p.calling_name, p.full_name FROM clinic_admissions ca LEFT JOIN patients p ON ca.patient_id = p.patient_id WHERE " . implode(' AND ', $where) . " ORDER BY ca.created_at DESC";
$res = $conn->query($sql);
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
}
?>
<div class="container">
    <div class="card">
        <h2>Clinic Admissions</h2>
        <a href="clinic_admission_form.php" class="btn btn-primary" style="margin-bottom:1rem;">+ New Admission</a>
        <form method="get" style="display:flex;gap:0.5rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap;">
            <label style="font-weight:600;">Filter:</label>
            <select name="filter_nursing_officer" style="padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
                <option value="">All Nursing Officers</option>
                <?php if ($nursing_officers): ?>
                    <?php while ($no = $nursing_officers->fetch_assoc()): ?>
                        <option value="<?php echo $no['nursing_id']; ?>" <?php echo ($filter_nursing === (string)$no['nursing_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($no['nursing_name']); ?></option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
            <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" style="padding:0.5rem;border-radius:6px;border:1px solid #ccc;">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="clinic_admissions_list.php" class="btn btn-secondary" style="text-decoration:none;padding:0.5rem;border-radius:6px;border:1px solid #ccc;background:#f5f5f5;color:#333;">Clear</a>
        </form>
        <?php if (empty($rows)): ?>
            <div style="padding:2rem;text-align:center;color:#666;">No clinic admissions found.</div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#667eea;color:white;">
                            <th style="padding:0.8rem;text-align:left;">Admission #</th>
                            <th style="padding:0.8rem;text-align:left;">Patient</th>
                            <th style="padding:0.8rem;text-align:left;">Date</th>
                            <th style="padding:0.8rem;text-align:left;">Weight</th>
                            <th style="padding:0.8rem;text-align:left;">PD Level</th>
                            <th style="padding:0.8rem;text-align:left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:0.6rem;"><?php echo htmlspecialchars($r['admission_number']); ?></td>
                            <td style="padding:0.6rem;"><?php echo htmlspecialchars($r['calling_name'] . ' (' . $r['full_name'] . ')'); ?></td>
                            <td style="padding:0.6rem;"><?php echo date('M j, Y H:i', strtotime($r['admission_date'])); ?></td>
                            <td style="padding:0.6rem;"><?php echo $r['weight'] ? htmlspecialchars($r['weight']) . ' kg' : '-'; ?></td>
                            <td style="padding:0.6rem;"><?php echo $r['pd_balance_ml'] ? htmlspecialchars($r['pd_balance_ml']) . ' ml' : '-'; ?></td>
                            <td style="padding:0.6rem;">
                                <a href="clinic_admission_form.php?id=<?php echo $r['admission_id']; ?>" class="btn btn-secondary">View / Edit</a>
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
                                    <form method="post" action="clinic_admission_form.php" style="display:inline;" onsubmit="return confirm('Delete this admission?');">
                                        <input type="hidden" name="admission_id" value="<?php echo $r['admission_id']; ?>">
                                        <button type="submit" name="delete_admission" class="btn btn-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
