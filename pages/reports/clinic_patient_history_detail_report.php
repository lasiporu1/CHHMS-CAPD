<?php
include '../../config/db.php';

// Get patient ID from URL parameter
$patient_id = $_GET['patient_id'] ?? '';
$export = $_GET['export'] ?? '';
$get_admission_details = $_GET['get_admission_details'] ?? '';
$admission_id = $_GET['admission_id'] ?? '';

// Handle AJAX request for clinic admission details
if ($get_admission_details && $admission_id && is_numeric($admission_id)) {
    // Get clinic admission info
    // clinic_admissions may not have admission_reason_id or attending_doctor_id in older schemas
    // select only available fields and provide safe defaults for reason and doctor info
    $admission_query = "SELECT ca.admission_id, ca.admission_date,
                                  ca.admission_number,
                                  NULL as reason_name,
                                  'Clinic' as doctor_info
                       FROM clinic_admissions ca
                       WHERE ca.admission_id = ?";
    $stmt = $conn->prepare($admission_query);
    $stmt->bind_param("i", $admission_id);
    $stmt->execute();
    $admission_result = $stmt->get_result();
    $admission = $admission_result->fetch_assoc();

    // Get investigations for this clinic admission (match by admission_number)
    $investigations_query = "SELECT investigation_type as type, investigation_name as name, result_values as results
                           FROM investigations WHERE admission_number = ? OR admission_id = ?";
    $stmt = $conn->prepare($investigations_query);
    $stmt->bind_param("si", $admission['admission_number'], $admission_id);
    $stmt->execute();
    $investigations_result = $stmt->get_result();
    $investigations = [];
    while ($row = $investigations_result->fetch_assoc()) {
        $investigations[] = $row;
    }

    // Get medicines for this clinic admission (match by admission_number)
    $medicines_query = "SELECT medicine_name as name, CONCAT(dosage, ' - ', route) as dosage_route,
                              DATE_FORMAT(start_date, '%d-%b-%y') as start_date,
                              DATE_FORMAT(end_date, '%d-%b-%y') as end_date
                       FROM medicines WHERE admission_number = ? OR admission_id = ?";
    $stmt = $conn->prepare($medicines_query);
    $stmt->bind_param("si", $admission['admission_number'], $admission_id);
    $stmt->execute();
    $medicines_result = $stmt->get_result();
    $medicines = [];
    while ($row = $medicines_result->fetch_assoc()) {
        $medicines[] = $row;
    }

    // Format admission dates (guard for missing fields)
    if (!empty($admission['admission_date'])) {
        $admission['admission_date'] = date('d-M-y', strtotime($admission['admission_date']));
    }
    if (isset($admission['discharge_date']) && $admission['discharge_date']) {
        $admission['discharge_date'] = date('d-M-y', strtotime($admission['discharge_date']));
    } else {
        $admission['discharge_date'] = null;
    }
    if (!isset($admission['discharge_notes'])) $admission['discharge_notes'] = null;

    header('Content-Type: application/json');
    echo json_encode([
        'admission' => $admission,
        'investigations' => $investigations,
        'medicines' => $medicines
    ]);
    exit;
}

// Validate patient ID
if (empty($patient_id) || !is_numeric($patient_id)) {
    header('Location: clinic_patient_detail_search.php');
    exit;
}

// Get patient basic information with nursing officer
$patient_query = "SELECT p.*, no.nursing_name
                  FROM patients p 
                  LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id
                  WHERE p.patient_id = ?";
$stmt = $conn->prepare($patient_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient_result = $stmt->get_result();

if ($patient_result->num_rows == 0) {
    header('Location: clinic_patient_detail_search.php?error=patient_not_found');
    exit;
}

$patient = $patient_result->fetch_assoc();

// Get counselling history for this patient (same as original)
$counselling_sql = "SELECT cs.*, no1.nursing_name AS nurse1, no2.nursing_name AS nurse2, no3.nursing_name AS nurse3
    FROM counselling_status cs
    LEFT JOIN nursing_officers no1 ON cs.first_nursing_officer_id = no1.nursing_id
    LEFT JOIN nursing_officers no2 ON cs.second_nursing_officer_id = no2.nursing_id
    LEFT JOIN nursing_officers no3 ON cs.third_nursing_officer_id = no3.nursing_id
    WHERE cs.patient_id = ?";
$stmt_counselling = $conn->prepare($counselling_sql);
$stmt_counselling->bind_param("i", $patient_id);
$stmt_counselling->execute();
$counselling_result = $stmt_counselling->get_result();
$counselling = $counselling_result->fetch_assoc();

// Build comprehensive data for all CLINIC admissions and associated investigations/medicines
    // Use clinic admissions and match investigations/medicines by admission_number or id.
    // Do not join admission_reasons or doctors because clinic_admissions may lack those columns.
    $comprehensive_query = "SELECT 
    ca.admission_id,
    DATE_FORMAT(ca.admission_date, '%d-%b-%y') as admission_date,
    ca.admission_number,
    NULL as reason_name,
    'Clinic' as doctor_info,
    i.investigation_type,
    i.investigation_name,
    i.result_values,
    m.medicine_name,
    CONCAT(m.dosage, ' - ', m.route) as dosage_route,
    DATE_FORMAT(m.start_date, '%d-%b-%y') as medicine_start_date,
    DATE_FORMAT(m.end_date, '%d-%b-%y') as medicine_end_date
FROM clinic_admissions ca
LEFT JOIN investigations i ON (i.admission_number = ca.admission_number OR i.admission_id = ca.admission_id)
LEFT JOIN medicines m ON (m.admission_number = ca.admission_number OR m.admission_id = ca.admission_id)
WHERE ca.patient_id = ?
ORDER BY ca.admission_date DESC";

$stmt_comp = $conn->prepare($comprehensive_query);
$stmt_comp->bind_param("i", $patient_id);
$stmt_comp->execute();
$comprehensive_result = $stmt_comp->get_result();

$organized_data = [];
while ($row = $comprehensive_result->fetch_assoc()) {
    $admission_key = $row['admission_id'] ?: $row['admission_number'];
    if (!isset($organized_data[$admission_key])) {
        $organized_data[$admission_key] = [
            'admission_date' => $row['admission_date'],
            'discharge_date' => $row['discharge_date'] ?? null,
            'discharge_notes' => $row['discharge_notes'] ?? null,
            'reason_name' => $row['reason_name'],
            'doctor_info' => $row['doctor_info'],
            'admission_number' => $row['admission_number'],
            'investigations' => [],
            'medicines' => []
        ];
    }
    if ($row['investigation_name'] && !in_array($row['investigation_name'], array_column($organized_data[$admission_key]['investigations'], 'name'))) {
        $organized_data[$admission_key]['investigations'][] = [
            'type' => $row['investigation_type'],
            'name' => $row['investigation_name'],
            'results' => $row['result_values']
        ];
    }
    if ($row['medicine_name'] && !in_array($row['medicine_name'], array_column($organized_data[$admission_key]['medicines'], 'name'))) {
        $organized_data[$admission_key]['medicines'][] = [
            'name' => $row['medicine_name'],
            'dosage_route' => $row['dosage_route'],
            'start_date' => $row['medicine_start_date'],
            'end_date' => $row['medicine_end_date']
        ];
    }
}

// Calculate patient age
$age = '';
if ($patient['date_of_birth']) {
    $dob = new DateTime($patient['date_of_birth']);
    $today = new DateTime();
    $age_calc = $today->diff($dob);
    $age = $age_calc->y . ' years, ' . $age_calc->m . ' months';
}

// Handle CSV export (similar to ward report)
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="clinic_patient_history_' . $patient['calling_name'] . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['', 'CLINIC PATIENT HISTORY REPORT', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, ['PATIENT INFORMATION', '', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, ['Calling Name', '', $patient['calling_name'], 'Age', $age, '', '', '', '', '', '']);
    fputcsv($output, ['Full Name', '', $patient['full_name'], 'Date of Birth', date('d-M-y', strtotime($patient['date_of_birth'])), '', '', '', '', '', '']);
    fputcsv($output, ['Clinic Number', '', $patient['clinic_number'], 'Hospital Number', $patient['hospital_number'] ?: '', '', '', '', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, ['DETAILED HISTORY', '', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, ['Admission Date', 'Clinic Admission #', 'Reason For Admission', 'Doctor', 'Investigation Type', 'Investigation', 'Investigation Results', 'Medicine', 'Dosage & Route', 'Start Date', 'End Date']);
    foreach ($organized_data as $admission) {
        $investigations = $admission['investigations'] ?: [['type' => '', 'name' => '', 'results' => '']];
        $medicines = $admission['medicines'] ?: [['name' => '', 'dosage_route' => '', 'start_date' => '', 'end_date' => '']];
        $max_rows = max(count($investigations), count($medicines));
        for ($i = 0; $i < $max_rows; $i++) {
            $investigation = $investigations[$i] ?? ['type' => '', 'name' => '', 'results' => ''];
            $medicine = $medicines[$i] ?? ['name' => '', 'dosage_route' => '', 'start_date' => '', 'end_date' => ''];
            fputcsv($output, [
                $i == 0 ? $admission['admission_date'] : '',
                $i == 0 ? $admission['admission_number'] : '',
                $i == 0 ? $admission['reason_name'] : '',
                $i == 0 ? $admission['doctor_info'] : '',
                $investigation['type'],
                $investigation['name'],
                $investigation['results'],
                $medicine['name'],
                $medicine['dosage_route'],
                $medicine['start_date'],
                $medicine['end_date']
            ]);
        }
    }
    exit;
}

include '../../includes/header.php';
?>

<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <div class="title-section">
                <h1 class="main-title">📋 Clinic Patient History Report</h1>
                <p class="subtitle">Clinic admission history for <?php echo htmlspecialchars($patient['calling_name']); ?></p>
            </div>
            <div class="action-buttons">
                <a href="?patient_id=<?php echo $patient_id; ?>&export=csv" class="btn btn-export">📊 Export CSV</a>
                <a href="clinic_patient_detail_search.php" class="btn btn-outline">← Back to Clinic Search</a>
                <a href="../../index.php" class="btn btn-outline">🏠 Dashboard</a>
            </div>
        </div>
    </div>

    <!-- Patient Information Section -->
    <div class="report-section">
        <div class="section-header">
            <h2>👤 Patient Information</h2>
        </div>
        <div class="patient-info-grid">
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Calling Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['calling_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value"><?php echo $age; ?></span>
                </div>
            </div>
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value"><?php echo date('d-M-y', strtotime($patient['date_of_birth'])); ?></span>
                </div>
            </div>
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Nursing Officer</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['nursing_name'] ?: 'Not assigned'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Clinic Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['clinic_number']); ?></span>
                </div>
            </div>
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Hospital Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['hospital_number'] ?: 'Not assigned'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Counselling History Section -->
    <?php if ($counselling): ?>
    <div class="report-section">
        <div class="section-header">
            <h2>🗣️ Counselling History</h2>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Date</th>
                    <th>Nursing Officer</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>First Counselling</td>
                    <td><?= htmlspecialchars($counselling['first_counselling_date'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($counselling['nurse1'] ?: '-') ?></td>
                </tr>
                <tr>
                    <td>Second Counselling</td>
                    <td><?= htmlspecialchars($counselling['second_counselling_date'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($counselling['nurse2'] ?: '-') ?></td>
                </tr>
                <tr>
                    <td>Third Counselling</td>
                    <td><?= htmlspecialchars($counselling['third_counselling_date'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($counselling['nurse3'] ?: '-') ?></td>
                </tr>
                <?php if (!empty($counselling['notes'])): ?>
                <tr>
                    <td colspan="3"><strong>Notes:</strong> <?= htmlspecialchars($counselling['notes']) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Detailed History Section -->
    <div class="report-section">
        <div class="section-header">
            <h2>🏥 Detailed History</h2>
        </div>
        <div class="detailed-history-table">
            <?php if (!empty($organized_data)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Admission Date</th>
                                <th>Clinic Admission #</th>
                                <th>Reason For Admission</th>
                                <th>Doctor</th>
                                <th>Investigation Type</th>
                                <th>Investigation</th>
                                <th>Investigation Results</th>
                                <th>Medicine</th>
                                <th>Dosage & Route</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($organized_data as $admission_id => $admission): ?>
                                <?php
                                $investigations = $admission['investigations'] ?: [['type' => '', 'name' => '', 'results' => '']];
                                $medicines = $admission['medicines'] ?: [['name' => '', 'dosage_route' => '', 'start_date' => '', 'end_date' => '']];
                                $max_rows = max(count($investigations), count($medicines));
                                for ($i = 0; $i < $max_rows; $i++):
                                    $investigation = $investigations[$i] ?? ['type' => '', 'name' => '', 'results' => ''];
                                    $medicine = $medicines[$i] ?? ['name' => '', 'dosage_route' => '', 'start_date' => '', 'end_date' => ''];
                                ?>
                                    <tr>
                                        <td><?php echo $i == 0 ? htmlspecialchars($admission['admission_date']) : ''; ?></td>
                                        <td><?php echo $i == 0 ? htmlspecialchars($admission['admission_number']) : ''; ?></td>
                                        <td><?php echo $i == 0 ? htmlspecialchars($admission['reason_name']) : ''; ?></td>
                                        <td><?php echo $i == 0 ? htmlspecialchars($admission['doctor_info']) : ''; ?></td>
                                        <td><?php echo htmlspecialchars($investigation['type']); ?></td>
                                        <td><?php echo htmlspecialchars($investigation['name']); ?></td>
                                        <td><?php echo htmlspecialchars($investigation['results']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['dosage_route']); ?></td>
                                        <td><?php echo $i == 0 ? '<button onclick="viewAdmissionDetails(' . $admission_id . ')" class="btn btn-sm btn-info">👁️ View</button>' : ''; ?></td>
                                    </tr>
                                <?php endfor; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">📋</div>
                    <h3>No Clinic History Found</h3>
                    <p>No clinic admission records found for this patient.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal and styles/scripts copied from patient report for consistent UI -->
<style>
/* Header Styles */
.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.title-section h1 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.subtitle {
    margin: 0.5rem 0 0 0;
    font-size: 1.1rem;
    opacity: 0.9;
}

.action-buttons {
    display: flex;
    gap: 1rem;
}

.btn { padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.5rem; }
.btn-export { background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.3); }
.btn-outline { background: transparent; color: white; border: 2px solid rgba(255,255,255,0.3); }
.report-section { background: white; margin-bottom: 2rem; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); overflow: hidden; }
.section-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 1.5rem 2rem; border-bottom: 1px solid #dee2e6; }
.section-header h2 { margin: 0; font-size: 1.5rem; font-weight: 600; color: #495057; }
.patient-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; padding: 2rem; }
.info-card { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #007bff; }
.info-row { display:flex; justify-content:space-between; margin-bottom:0.5rem; }
.info-label { font-weight:600; color:#495057; }
.info-value { color:#333; }
.data-table { width:100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 0.75rem; border-bottom:1px solid #eee; text-align:left; }
.table-responsive { overflow:auto; }
.no-data { text-align:center; padding:2rem; }
.no-data-icon { font-size:3rem; }
/* Modal styles */
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background: rgba(0,0,0,0.4); }
.modal-content { background:white; margin:5% auto; padding:1rem; border-radius:8px; width:90%; max-width:900px; }
.modal-header { display:flex; justify-content:space-between; align-items:center; padding-bottom:0.5rem; border-bottom:1px solid #eee; }
.close-btn { background:transparent; border:none; font-size:1.5rem; cursor:pointer; }
.modal-section { margin-top:1rem; }
.modal-section-title { font-weight:700; margin-bottom:0.5rem; }
.modal-info-grid { display:grid; grid-template-columns: repeat(2,1fr); gap:1rem; }
.modal-info-item { background:#f8f9fa; padding:0.75rem; border-radius:6px; }
.modal-info-label { font-weight:600; color:#555; }
.modal-info-value { color:#222; margin-top:0.25rem; }
.modal-table { width:100%; border-collapse:collapse; margin-top:0.5rem; }
.modal-table th { background:#f1f1f1; padding:0.5rem; font-weight:600; }
.modal-table td { padding:0.75rem; border-bottom:1px solid #eee; }
</style>

<!-- Admission Details Modal -->
<div id="admissionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Admission Details</h2>
            <button class="close-btn" onclick="closeAdmissionModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script>
function viewAdmissionDetails(admissionId) {
    // Fetch admission data via AJAX
    fetch('clinic_patient_history_detail_report.php?patient_id=<?php echo $patient_id; ?>&get_admission_details=1&admission_id=' + admissionId)
        .then(response => response.json())
        .then(data => {
            displayAdmissionDetails(data);
        })
        .catch(error => console.error('Error:', error));
}

function displayAdmissionDetails(data) {
    const modal = document.getElementById('admissionModal');
    const modalBody = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');
    let html = `
        <div class="modal-section">
            <div class="modal-section-title">🏥 Admission Information</div>
            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <div class="modal-info-label">Admission Date</div>
                    <div class="modal-info-value">${data.admission.admission_date}</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">Admission #</div>
                    <div class="modal-info-value">${data.admission.admission_number}</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">Reason for Admission</div>
                    <div class="modal-info-value">${data.admission.reason_name || '-'}</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">Doctor</div>
                    <div class="modal-info-value">${data.admission.doctor_info || 'Clinic'}</div>
                </div>
            </div>
        </div>
    `;
    
    // Investigations section
    if (data.investigations && data.investigations.length > 0) {
        html += `
            <div class="modal-section">
                <div class="modal-section-title">🔬 Investigations</div>
                <table class="modal-table">
                    <thead>
                        <tr>
                            <th>Investigation Type</th>
                            <th>Investigation Name</th>
                            <th>Results</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        data.investigations.forEach(inv => {
            html += `
                        <tr>
                            <td>${inv.type}</td>
                            <td>${inv.name}</td>
                            <td>${inv.results}</td>
                        </tr>
            `;
        });
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    // Medicines section
    if (data.medicines && data.medicines.length > 0) {
        html += `
            <div class="modal-section">
                <div class="modal-section-title">💊 Medicines</div>
                <table class="modal-table">
                    <thead>
                        <tr>
                            <th>Medicine Name</th>
                            <th>Dosage & Route</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        data.medicines.forEach(med => {
            html += `
                        <tr>
                            <td>${med.name}</td>
                            <td>${med.dosage_route}</td>
                            <td>${med.start_date}</td>
                            <td>${med.end_date}</td>
                        </tr>
            `;
        });
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    modalTitle.textContent = 'Admission Details - ' + (data.admission.admission_date || 'Details');
    modalBody.innerHTML = html;
    modal.style.display = 'block';
}

function closeAdmissionModal() {
    document.getElementById('admissionModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('admissionModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include '../../includes/footer.php';
