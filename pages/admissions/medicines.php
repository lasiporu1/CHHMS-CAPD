<?php
session_start();
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Determine whether we're viewing a ward admission (admission_id)
// or a clinic admission (admission_number)
$is_clinic = false;
$admission_id = null;
if (isset($_GET['admission_number']) && !empty($_GET['admission_number'])) {
    $admission_number = $conn->real_escape_string($_GET['admission_number']);
    $ca_sql = "SELECT ca.*, p.calling_name, p.full_name, p.nic FROM clinic_admissions ca LEFT JOIN patients p ON ca.patient_id = p.patient_id WHERE ca.admission_number = '" . $admission_number . "'";
    $ca_res = $conn->query($ca_sql);
    if (!$ca_res || $ca_res->num_rows == 0) {
        header("Location: ../clinics/clinic_admissions_list.php");
        exit();
    }
    $admission = $ca_res->fetch_assoc();
    $admission_id = $admission['admission_id'];
    $is_clinic = true;
} elseif (isset($_GET['admission_id']) && !empty($_GET['admission_id'])) {
    $admission_id = $conn->real_escape_string($_GET['admission_id']);
} else {
    header("Location: admission_list.php");
    exit();
}

// Resolve patient_id early so we can list medicines across all admissions
$patient_id = null;
if ($is_clinic) {
    $patient_id = !empty($admission['patient_id']) ? (int)$admission['patient_id'] : null;
} elseif (!empty($admission_id)) {
    $tmp = $conn->query("SELECT patient_id FROM ward_admissions WHERE admission_id = $admission_id LIMIT 1");
    if ($tmp && $tmp->num_rows > 0) {
        $patient_id = (int)$tmp->fetch_assoc()['patient_id'];
    }
}
// Allow passing patient_id explicitly
if (isset($_GET['patient_id']) && !empty($_GET['patient_id'])) {
    $patient_id = (int)$conn->real_escape_string($_GET['patient_id']);
}

// Load patient basic details for header display
$p = ['calling_name' => '', 'full_name' => '', 'clinic_number' => '', 'nic' => '', 'hospital_number' => ''];
if ($is_clinic && !empty($admission)) {
    if (!empty($admission['calling_name'])) $p['calling_name'] = $admission['calling_name'];
    if (!empty($admission['full_name'])) $p['full_name'] = $admission['full_name'];
    if (!empty($admission['clinic_number'])) $p['clinic_number'] = $admission['clinic_number'];
    if (!empty($admission['nic'])) $p['nic'] = $admission['nic'];
    if (!empty($admission['hospital_number'])) $p['hospital_number'] = $admission['hospital_number'];
} elseif (!empty($patient_id)) {
    $pq = $conn->query("SELECT calling_name, full_name, clinic_number, nic, hospital_number FROM patients WHERE patient_id = " . (int)$patient_id . " LIMIT 1");
    if ($pq && $pq->num_rows > 0) {
        $pp = $pq->fetch_assoc();
        $p['calling_name'] = $pp['calling_name'];
        $p['full_name'] = $pp['full_name'];
        $p['clinic_number'] = $pp['clinic_number'];
        $p['nic'] = $pp['nic'];
        $p['hospital_number'] = $pp['hospital_number'];
    }
}

// Filters from GET
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$start_from = isset($_GET['start_from']) ? $conn->real_escape_string($_GET['start_from']) : '';
$start_to = isset($_GET['start_to']) ? $conn->real_escape_string($_GET['start_to']) : '';
$end_from = isset($_GET['end_from']) ? $conn->real_escape_string($_GET['end_from']) : '';
$end_to = isset($_GET['end_to']) ? $conn->real_escape_string($_GET['end_to']) : '';
$status_filter = isset($_GET['status_filter']) ? $conn->real_escape_string($_GET['status_filter']) : '';

// Build WHERE clauses
$where = array();
if (!empty($patient_id)) {
    $where[] = "m.patient_id = " . (int)$patient_id;
} else {
    if ($is_clinic) {
        $where[] = "m.admission_number = '" . $conn->real_escape_string($admission['admission_number']) . "'";
    } else {
        $where[] = "m.admission_id = " . (int)$admission_id;
    }
}
if ($search !== '') {
    $where[] = "(m.medicine_name LIKE '%$search%' OR m.generic_name LIKE '%$search%')";
}
if (!empty($start_from)) $where[] = "m.start_date >= '$start_from'";
if (!empty($start_to)) $where[] = "m.start_date <= '$start_to'";
if (!empty($end_from)) $where[] = "m.end_date >= '$end_from'";
if (!empty($end_to)) $where[] = "m.end_date <= '$end_to'";
if (!empty($status_filter)) $where[] = "m.status = '$status_filter'";

$sql = "SELECT m.*, u.username AS prescribed_by_name FROM medicines m LEFT JOIN users u ON m.prescribed_by = u.user_id";
if (count($where) > 0) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY COALESCE(m.start_date, m.created_at) DESC";
$result = $conn->query($sql);

?>
<?php include '../../includes/header.php'; ?>

<div class="container">
    <div class="card">
        <div class="header">
            <div>
                <h2>Patient Medicines</h2>
                <div style="font-size:0.95rem;color:#444;margin-top:6px;">
                    <?php echo htmlspecialchars(($p['calling_name'] ?: '-') . ' (' . ($p['full_name'] ?: '-') . ')'); ?>
                    &mdash; Clinic: <?php echo htmlspecialchars($p['clinic_number'] ?: '-'); ?>
                    &mdash; PHN: <?php echo htmlspecialchars($p['hospital_number'] ?: '-'); ?>
                    &mdash; NIC: <?php echo htmlspecialchars($p['nic'] ?: '-'); ?>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger" style="background:#f8d7da;color:#721c24;padding:1rem;border-radius:8px;margin-bottom:1rem;">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
            <?php if ($is_clinic): ?>
                <a class="btn btn-warning" href="medicine_form.php?admission_number=<?php echo urlencode($admission['admission_number']); ?>">Prescribe Medicine</a>
            <?php else: ?>
                <a class="btn btn-warning" href="medicine_form.php?admission_id=<?php echo htmlspecialchars($admission_id); ?>">Prescribe Medicine</a>
            <?php endif; ?>
        </div>

        <!-- Filter form: search, date ranges, status -->
        <form method="GET" style="margin:0 0 1rem 0; display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <?php if ($is_clinic): ?>
                <input type="hidden" name="admission_number" value="<?php echo htmlspecialchars($admission['admission_number']); ?>">
            <?php else: ?>
                <input type="hidden" name="admission_id" value="<?php echo htmlspecialchars($admission_id); ?>">
            <?php endif; ?>
            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>">
            <input type="text" name="search" placeholder="Medicine name" value="<?php echo htmlspecialchars($search); ?>" style="padding:0.4rem;border:1px solid #ddd;border-radius:4px;min-width:180px;">
            <label style="font-size:0.9rem;color:#444;">Start</label>
            <input type="date" name="start_from" value="<?php echo htmlspecialchars($start_from); ?>" style="padding:0.35rem;border:1px solid #ddd;border-radius:4px;">
            <input type="date" name="start_to" value="<?php echo htmlspecialchars($start_to); ?>" style="padding:0.35rem;border:1px solid #ddd;border-radius:4px;">
            <label style="font-size:0.9rem;color:#444;">End</label>
            <input type="date" name="end_from" value="<?php echo htmlspecialchars($end_from); ?>" style="padding:0.35rem;border:1px solid #ddd;border-radius:4px;">
            <input type="date" name="end_to" value="<?php echo htmlspecialchars($end_to); ?>" style="padding:0.35rem;border:1px solid #ddd;border-radius:4px;">
            <select name="status_filter" style="padding:0.35rem;border:1px solid #ddd;border-radius:4px;">
                <option value="">Any status</option>
                <option value="Active" <?php echo ($status_filter=='Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Completed" <?php echo ($status_filter=='Completed') ? 'selected' : ''; ?>>Completed</option>
                <option value="On Hold" <?php echo ($status_filter=='On Hold') ? 'selected' : ''; ?>>On Hold</option>
                <option value="Omit" <?php echo ($status_filter=='Omit') ? 'selected' : ''; ?>>Omit</option>
                <option value="Discontinued" <?php echo ($status_filter=='Discontinued') ? 'selected' : ''; ?>>Discontinued</option>
            </select>
            <button type="submit" class="btn" style="background:#2d87f0;color:#fff;padding:0.45rem 0.6rem;border-radius:6px;border:none;">Search</button>
            <?php if ($is_clinic): ?>
                <a class="btn" href="medicines.php?admission_number=<?php echo urlencode($admission['admission_number']); ?>&patient_id=<?php echo htmlspecialchars($patient_id); ?>" style="background:#6c757d;color:#fff;padding:0.45rem 0.6rem;border-radius:6px;text-decoration:none;">Clear</a>
            <?php else: ?>
                <a class="btn" href="medicines.php?admission_id=<?php echo htmlspecialchars($admission_id); ?>&patient_id=<?php echo htmlspecialchars($patient_id); ?>" style="background:#6c757d;color:#fff;padding:0.45rem 0.6rem;border-radius:6px;text-decoration:none;">Clear</a>
            <?php endif; ?>
        </form>

        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Ward/Clinic</th>
                        <th>Medicine Name</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Route</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Day Count</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo !empty($r['created_at']) ? date('d/m/Y', strtotime($r['created_at'])) : '-'; ?></td>
                            <td>
                                <?php
                                    if (!empty($r['admission_number'])) echo 'Clinic: ' . htmlspecialchars($r['admission_number']);
                                    elseif (!empty($r['admission_id'])) echo 'Ward: #' . str_pad((int)$r['admission_id'],4,'0',STR_PAD_LEFT);
                                    else echo '-';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['medicine_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['dosage']); ?></td>
                            <td><?php echo htmlspecialchars($r['frequency']); ?></td>
                            <td><?php echo htmlspecialchars($r['route']); ?></td>
                            <td><?php echo !empty($r['start_date']) ? date('d/m/Y', strtotime($r['start_date'])) : '-'; ?></td>
                            <td><?php echo !empty($r['end_date']) ? date('d/m/Y', strtotime($r['end_date'])) : '-'; ?></td>
                            <td>
                                <?php
                                    if (!empty($r['start_date']) && !empty($r['end_date'])) {
                                        $days = (int) floor((strtotime($r['end_date']) - strtotime($r['start_date'])) / 86400);
                                        if ($days < 0) $days = 0;
                                        echo $days;
                                    } else echo '-';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['status']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align:center;padding:1.5rem;color:#6c757d">No medications found for this patient.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
