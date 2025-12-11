<?php
include '../../config/db.php';

// Get patient ID from URL parameter
$patient_id = $_GET['patient_id'] ?? '';
$export = $_GET['export'] ?? '';
$get_admission_details = $_GET['get_admission_details'] ?? '';
$admission_id = $_GET['admission_id'] ?? '';

// Handle AJAX request for admission details
if ($get_admission_details && $admission_id && is_numeric($admission_id)) {
    // Get admission info
    $admission_query = "SELECT wa.admission_id, wa.admission_date, wa.discharge_date, 
                                                          wa.discharge_notes,
                              ar.reason_name, CONCAT(COALESCE(d.specialization, 'Ward Doctor'), ' - Dr. ', d.doctor_name) as doctor_info
                       FROM ward_admissions wa
                       LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
                       LEFT JOIN doctors d ON wa.attending_doctor_id = d.doctor_id
                       WHERE wa.admission_id = ?";
    $stmt = $conn->prepare($admission_query);
    $stmt->bind_param("i", $admission_id);
    $stmt->execute();
    $admission_result = $stmt->get_result();
    $admission = $admission_result->fetch_assoc();
    
    // Get investigations
    $investigations_query = "SELECT investigation_type as type, investigation_name as name, result_values as results
                           FROM investigations WHERE admission_id = ?";
    $stmt = $conn->prepare($investigations_query);
    $stmt->bind_param("i", $admission_id);
    $stmt->execute();
    $investigations_result = $stmt->get_result();
    $investigations = [];
    while ($row = $investigations_result->fetch_assoc()) {
        $investigations[] = $row;
    }
    
    // Get medicines
    $medicines_query = "SELECT medicine_name as name, CONCAT(dosage, ' - ', route) as dosage_route,
                              DATE_FORMAT(start_date, '%d-%b-%y') as start_date,
                              DATE_FORMAT(end_date, '%d-%b-%y') as end_date
                       FROM medicines WHERE admission_id = ?";
    $stmt = $conn->prepare($medicines_query);
    $stmt->bind_param("i", $admission_id);
    $stmt->execute();
    $medicines_result = $stmt->get_result();
    $medicines = [];
    while ($row = $medicines_result->fetch_assoc()) {
        $medicines[] = $row;
    }
    
    // Format admission date
    $admission['admission_date'] = date('d-M-y', strtotime($admission['admission_date']));
    $admission['discharge_date'] = $admission['discharge_date'] ? date('d-M-y', strtotime($admission['discharge_date'])) : null;
    
    // Return JSON response
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
    header('Location: patient_search.php');
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
    header('Location: patient_search.php?error=patient_not_found');
    exit;
}

$patient = $patient_result->fetch_assoc();

// Get comprehensive data for all admissions with associated investigations and medicines
$comprehensive_query = "SELECT 
    wa.admission_id,
    DATE_FORMAT(wa.admission_date, '%d-%b-%y') as admission_date,
    DATE_FORMAT(wa.discharge_date, '%d-%b-%y') as discharge_date,
    wa.discharge_notes,
    ar.reason_name,
    CONCAT(COALESCE(d.specialization, 'Ward Doctor'), ' - Dr. ', d.doctor_name) as doctor_info,
    i.investigation_type,
    i.investigation_name,
    i.result_values,
    m.medicine_name,
    CONCAT(m.dosage, ' - ', m.route) as dosage_route,
    DATE_FORMAT(m.start_date, '%d-%b-%y') as medicine_start_date,
    DATE_FORMAT(m.end_date, '%d-%b-%y') as medicine_end_date
FROM ward_admissions wa
LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
LEFT JOIN doctors d ON wa.attending_doctor_id = d.doctor_id
LEFT JOIN investigations i ON wa.admission_id = i.admission_id
LEFT JOIN medicines m ON wa.admission_id = m.admission_id
WHERE wa.patient_id = ?
ORDER BY wa.admission_date DESC, i.investigation_id, m.medicine_id";

$stmt_comp = $conn->prepare($comprehensive_query);
$stmt_comp->bind_param("i", $patient_id);
$stmt_comp->execute();
$comprehensive_result = $stmt_comp->get_result();

// Organize data by admission
$organized_data = [];
while ($row = $comprehensive_result->fetch_assoc()) {
    $admission_key = $row['admission_id'];
    
    if (!isset($organized_data[$admission_key])) {
        $organized_data[$admission_key] = [
            'admission_date' => $row['admission_date'],
            'discharge_date' => $row['discharge_date'],
                        'discharge_notes' => $row['discharge_notes'],
            'reason_name' => $row['reason_name'],
            'doctor_info' => $row['doctor_info'],
            'investigations' => [],
            'medicines' => []
        ];
    }
    
    // Add investigations
    if ($row['investigation_name'] && !in_array($row['investigation_name'], array_column($organized_data[$admission_key]['investigations'], 'name'))) {
        $organized_data[$admission_key]['investigations'][] = [
            'type' => $row['investigation_type'],
            'name' => $row['investigation_name'],
            'results' => $row['result_values']
        ];
    }
    
    // Add medicines
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

// Handle CSV export
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="patient_history_' . $patient['calling_name'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header row with empty first column
    fputcsv($output, ['', 'PATIENT HISTORY REPORT', '', '', '', '', '', '', '', '', '']);
    
    // Patient Information section
    fputcsv($output, ['PATIENT INFORMATION', '', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '']);
    
    // Patient info in the format from CSV
    fputcsv($output, ['Calling Name', '', $patient['calling_name'], 'Age', $age, '', '', '', '', '', '']);
    fputcsv($output, ['Full Name', '', $patient['full_name'], 'Date of Birth', date('d-M-y', strtotime($patient['date_of_birth'])), '', '', '', '', '', '']);
    fputcsv($output, ['Nursing Officer', '', $patient['nursing_name'] ?: '', 'Blood Group', $patient['blood_group'] ?: '', '', '', '', '', '', '']);
    fputcsv($output, ['Clinic Number', '', $patient['clinic_number'], 'Hospital Number', $patient['hospital_number'] ?: '', '', '', '', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '']);
    
    // Detailed History section
    fputcsv($output, ['DETAILED HISTORY', '', '', '', '', '', '', '', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '']);
    
    // Table headers
    fputcsv($output, ['Admission Date', 'Reason For Admission', 'Doctor', 'Investigation Type', 'Investigation', 'Investigation Results', 'Medicine', 'Dosage & Route', 'Start Date', 'End Date', 'Discharge Date']);
    
    // Generate rows for each admission
    foreach ($organized_data as $admission) {
        $investigations = $admission['investigations'] ?: [['type' => '', 'name' => '', 'results' => '']];
        $medicines = $admission['medicines'] ?: [['name' => '', 'dosage_route' => '', 'start_date' => '', 'end_date' => '']];
        
        $max_rows = max(count($investigations), count($medicines));
        
        for ($i = 0; $i < $max_rows; $i++) {
            $investigation = $investigations[$i] ?? ['type' => '', 'name' => '', 'results' => ''];
            $medicine = $medicines[$i] ?? ['name' => '', 'dosage_route' => '', 'start_date' => '', 'end_date' => ''];
            
            fputcsv($output, [
                $i == 0 ? $admission['admission_date'] : '',
                $i == 0 ? $admission['reason_name'] : '',
                $i == 0 ? $admission['doctor_info'] : '',
                $investigation['type'],
                $investigation['name'],
                $investigation['results'],
                $medicine['name'],
                $medicine['dosage_route'],
                $medicine['start_date'],
                $medicine['end_date'],
                $i == 0 ? $admission['discharge_date'] : ''
            ]);
        }
    }
    
    // Add empty rows at the end
    for ($i = 0; $i < 6; $i++) {
        fputcsv($output, ['', '', '', '', '', '', '', '', '', '', '']);
    }
    
    fclose($output);
    exit;
}

// Include header only if not exporting
include '../../includes/header.php';
?>

<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <div class="title-section">
                <h1 class="main-title">📋 Patient History Report</h1>
                <p class="subtitle">Comprehensive medical history for <?php echo htmlspecialchars($patient['calling_name']); ?></p>
            </div>
            <div class="action-buttons">
                <a href="?patient_id=<?php echo $patient_id; ?>&export=csv" class="btn btn-export">📊 Export CSV</a>
                <a href="patient_detail_search.php" class="btn btn-outline">← Back to Search</a>
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
                    <span class="info-label">Blood Group</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['blood_group'] ?: 'Not specified'); ?></span>
                </div>
            </div>
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Clinic Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['clinic_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Hospital Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($patient['hospital_number'] ?: 'Not assigned'); ?></span>
                </div>
            </div>
        </div>
    </div>

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
                                <th>Reason For Admission</th>
                                <th>Doctor</th>
                                <th>Investigation Type</th>
                                <th>Investigation</th>
                                <th>Investigation Results</th>
                                <th>Medicine</th>
                                <th>Dosage & Route</th>
                                <th>Discharge Date</th>
                                                                <th>Discharge Notes</th>
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
                                        <td><?php echo $i == 0 ? htmlspecialchars($admission['reason_name']) : ''; ?></td>
                                        <td><?php echo $i == 0 ? htmlspecialchars($admission['doctor_info']) : ''; ?></td>
                                        <td><?php echo htmlspecialchars($investigation['type']); ?></td>
                                        <td><?php echo htmlspecialchars($investigation['name']); ?></td>
                                        <td><?php echo htmlspecialchars($investigation['results']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['dosage_route']); ?></td>
                                        <td><?php echo $i == 0 ? htmlspecialchars($admission['discharge_date']) : ''; ?></td>
                                                                                <td><?php echo $i == 0 ? (htmlspecialchars($admission['discharge_notes']) ?: '<span style="color: #999;">-</span>') : ''; ?></td>
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
                    <h3>No History Found</h3>
                    <p>No medical history records found for this patient.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

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

.btn {
    padding: 0.75rem 1.5rem;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-export {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
}

.btn-export:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

.btn-outline {
    background: transparent;
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
}

.btn-outline:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.5);
}

/* Report Section Styles */
.report-section {
    background: white;
    margin-bottom: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    overflow: hidden;
}

.section-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #dee2e6;
}

.section-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #495057;
}

/* Patient Info Grid */
.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    padding: 2rem;
}

.info-card {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.9rem;
}

.info-value {
    font-weight: 500;
    color: #212529;
}

/* Table Styles */
.table-responsive {
    padding: 0 2rem 2rem;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
    min-width: 1200px;
}

.data-table th,
.data-table td {
    padding: 1rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
    vertical-align: top;
}

.data-table th {
    background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table tbody tr:hover {
    background-color: #f8f9fa;
}

.data-table td:empty::after {
    content: "";
    display: inline-block;
}

/* No Data Style */
.no-data {
    text-align: center;
    padding: 4rem 2rem;
    color: #6c757d;
}

.no-data-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.no-data h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: #495057;
}

.no-data p {
    margin: 0;
    font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
        padding: 0 1rem;
    }
    
    .title-section h1 {
        font-size: 2rem;
    }
    
    .patient-info-grid {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
    
    .info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .table-responsive {
        padding: 0 1rem 1rem;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.75rem 0.5rem;
    }
}

@media print {
    .action-buttons { display: none !important; }
    .header-section { background: white !important; color: black !important; }
    .data-table th { background: #f8f9fa !important; color: black !important; }
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 95%;
    max-width: 900px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    max-height: 85vh;
    overflow-y: auto;
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
}

.close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    padding: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 1.5rem;
}

.modal-section {
    margin-bottom: 2rem;
}

.modal-section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #667eea;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.modal-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.modal-info-item {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    border-left: 3px solid #667eea;
}

.modal-info-label {
    font-weight: 600;
    color: #667eea;
    font-size: 0.9rem;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
}

.modal-info-value {
    color: #2c3e50;
    font-size: 1rem;
}

.modal-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.modal-table thead {
    background: #f8f9fa;
}

.modal-table th {
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 2px solid #ddd;
}

.modal-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #eee;
}

.modal-table tbody tr:hover {
    background: #f8f9fa;
}
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
    fetch('patient_history_detail_report.php?patient_id=<?php echo $patient_id; ?>&get_admission_details=1&admission_id=' + admissionId)
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
                    <div class="modal-info-label">Discharge Date</div>
                    <div class="modal-info-value">${data.admission.discharge_date || 'Still admitted'}</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">Reason for Admission</div>
                    <div class="modal-info-value">${data.admission.reason_name}</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">Doctor</div>
                    <div class="modal-info-value">${data.admission.doctor_info}</div>
                                ${data.admission.discharge_notes ? `<div class="modal-info-item" style="grid-column: 1 / -1;">
                                    <div class="modal-info-label">Discharge Notes</div>
                                    <div class="modal-info-value" style="white-space: pre-wrap;">${data.admission.discharge_notes}</div>
                                </div>` : ''}
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
    
    modalTitle.textContent = 'Admission Details - ' + data.admission.admission_date;
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

<?php include '../../includes/footer.php'; ?>