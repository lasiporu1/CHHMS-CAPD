<?php
include '../../config/db.php';

// Get patient ID from URL parameter
$patient_id = $_GET['patient_id'] ?? '';
$export = $_GET['export'] ?? '';

// Validate patient ID
if (empty($patient_id) || !is_numeric($patient_id)) {
    header('Location: patient_search.php');
    exit;
}

// Get patient basic information
$patient_query = "SELECT patient_id, calling_name, full_name, clinic_number, hospital_number, nic, 
                  date_of_birth, address, contact_number
                  FROM patients WHERE patient_id = ?";
$stmt = $conn->prepare($patient_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient_result = $stmt->get_result();

if ($patient_result->num_rows == 0) {
    header('Location: patient_search.php?error=patient_not_found');
    exit;
}

$patient = $patient_result->fetch_assoc();

// Get all admissions with details
$admissions_query = "SELECT wa.*, ar.reason_name, d.doctor_name, d.specialization, no.nursing_name,
                     DATE_FORMAT(wa.admission_date, '%M %d, %Y at %h:%i %p') as formatted_admission_date,
                     DATE_FORMAT(wa.discharge_date, '%M %d, %Y at %h:%i %p') as formatted_discharge_date,
                     DATEDIFF(COALESCE(wa.discharge_date, CURDATE()), wa.admission_date) as stay_duration
                     FROM ward_admissions wa
                     LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
                     LEFT JOIN doctors d ON wa.attending_doctor_id = d.doctor_id
                     LEFT JOIN nursing_officers no ON wa.nursing_officer_id = no.nursing_id
                     WHERE wa.patient_id = ?
                     ORDER BY wa.admission_date DESC";

$stmt = $conn->prepare($admissions_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$admissions_result = $stmt->get_result();

// Get investigation history across all admissions
$investigations_query = "SELECT i.*, wa.admission_id, wa.admission_date,
                        DATE_FORMAT(i.ordered_date, '%M %d, %Y') as formatted_ordered_date,
                        DATE_FORMAT(i.result_date, '%M %d, %Y') as formatted_result_date,
                        u.username as ordered_by_name, ar.reason_name as admission_reason
                        FROM investigations i
                        LEFT JOIN ward_admissions wa ON i.admission_id = wa.admission_id
                        LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
                        LEFT JOIN users u ON i.ordered_by = u.user_id
                        WHERE wa.patient_id = ?
                        ORDER BY i.ordered_date DESC, i.ordered_time DESC";

$stmt_inv = $conn->prepare($investigations_query);
$stmt_inv->bind_param("i", $patient_id);
$stmt_inv->execute();
$investigations_result = $stmt_inv->get_result();

// Get medicine history across all admissions
$medicines_query = "SELECT m.*, wa.admission_id, wa.admission_date,
                   DATE_FORMAT(m.start_date, '%M %d, %Y') as formatted_start_date,
                   DATE_FORMAT(m.end_date, '%M %d, %Y') as formatted_end_date,
                   u.username as prescribed_by_name, ar.reason_name as admission_reason
                   FROM medicines m
                   LEFT JOIN ward_admissions wa ON m.admission_id = wa.admission_id
                   LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
                   LEFT JOIN users u ON m.prescribed_by = u.user_id
                   WHERE wa.patient_id = ?
                   ORDER BY m.start_date DESC";

$stmt_med = $conn->prepare($medicines_query);
$stmt_med->bind_param("i", $patient_id);
$stmt_med->execute();
$medicines_result = $stmt_med->get_result();

// Calculate patient age
$age = '';
if ($patient['date_of_birth']) {
    $dob = new DateTime($patient['date_of_birth']);
    $today = new DateTime();
    $age_calc = $today->diff($dob);
    $age = $age_calc->y . ' years, ' . $age_calc->m . ' months';
}

// Handle CSV export BEFORE including header
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="patient_history_' . $patient['calling_name'] . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8 in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Patient info header
    fputcsv($output, ['PATIENT INFORMATION']);
    fputcsv($output, ['Name', $patient['calling_name'] . ' (' . $patient['full_name'] . ')']);
    fputcsv($output, ['Clinic Number', $patient['clinic_number']]);
    fputcsv($output, ['Hospital Number', $patient['hospital_number']]);
    fputcsv($output, ['NIC', $patient['nic']]);
    fputcsv($output, ['Age', $age]);
    fputcsv($output, ['Address', $patient['address']]);
    fputcsv($output, ['Contact', $patient['contact_number']]);
    fputcsv($output, ['']);
    
    // Admissions header
    fputcsv($output, ['ADMISSION HISTORY']);
    fputcsv($output, [
        'Admission Date', 'Discharge Date', 'Reason', 'Doctor', 'Nursing Officer', 'Status', 'Stay Duration (Days)', 'Notes'
    ]);
    
    // Reset result pointer
    $stmt->execute();
    $admissions_result = $stmt->get_result();
    
    while ($row = $admissions_result->fetch_assoc()) {
        $doctor_info = '';
        if ($row['doctor_name']) {
            $doctor_info = ($row['specialization'] ? $row['specialization'] . ' - ' : '') . 'Dr. ' . $row['doctor_name'];
        }
        
        fputcsv($output, [
            $row['formatted_admission_date'],
            $row['formatted_discharge_date'] ?: 'Still admitted',
            $row['reason_name'],
            $doctor_info,
            $row['nursing_name'],
            $row['discharge_status'],
            $row['stay_duration'],
            $row['notes'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// Include header only if not exporting
include '../../includes/header.php';

// Reset result for display if export was not requested
if ($export !== 'csv') {
    $stmt->execute();
    $admissions_result = $stmt->get_result();
}
?>

<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <div class="title-section">
                <h1 class="main-title">📋 Patient History Report</h1>
                <p class="subtitle">Complete medical history and admission records</p>
            </div>
            <div class="action-buttons">
                <a href="patient_search.php" class="btn btn-outline">🔍 Search Another Patient</a>
                <a href="report_list.php" class="btn btn-outline">← Back to Reports</a>
                <a href="../../index.php" class="btn btn-outline">🏠 Dashboard</a>
            </div>
        </div>
    </div>

    <!-- Patient Information Card -->
    <div class="patient-info-card">
        <div class="card-header">
            <h3>👤 Patient Information</h3>
            <div class="export-actions">
                <?php if ($admissions_result->num_rows > 0): ?>
                    <a href="?patient_id=<?php echo $patient_id; ?>&export=csv" class="btn btn-success">📊 Export Full History</a>
                <?php endif; ?>
                <button type="button" onclick="window.print()" class="btn btn-info">🖨️ Print Report</button>
            </div>
        </div>
        
        <div class="patient-details">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="label">Full Name:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Calling Name:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['calling_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Clinic Number:</span>
                    <span class="value badge badge-blue"><?php echo htmlspecialchars($patient['clinic_number']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Hospital Number:</span>
                    <span class="value badge badge-purple"><?php echo htmlspecialchars($patient['hospital_number'] ?: 'Not assigned'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">NIC:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['nic']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Age:</span>
                    <span class="value"><?php echo $age; ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Date of Birth:</span>
                    <span class="value"><?php echo date('F j, Y', strtotime($patient['date_of_birth'])); ?></span>
                </div>
                <div class="detail-item full-width">
                    <span class="label">Address:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['address']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Contact Number:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['contact_number'] ?: 'Not provided'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Admission History -->
    <div class="history-section">
        <div class="history-header">
            <h3>🏥 Admission History</h3>
            <div class="history-summary">
                <span class="total-admissions"><?php echo $admissions_result->num_rows; ?></span>
                <span class="summary-label">Total Admissions</span>
            </div>
        </div>

        <?php if ($admissions_result->num_rows > 0): ?>
            <div class="timeline">
                <?php 
                $admission_count = 0;
                while ($admission = $admissions_result->fetch_assoc()): 
                    $admission_count++;
                    $is_current = empty($admission['discharge_date']);
                ?>
                    <div class="timeline-item <?php echo $is_current ? 'current' : 'completed'; ?>">
                        <div class="timeline-marker">
                            <span class="admission-number"><?php echo $admission_count; ?></span>
                        </div>
                        <div class="timeline-content">
                            <div class="admission-card">
                                <div class="admission-header">
                                    <div class="admission-title">
                                        <h4><?php echo htmlspecialchars($admission['reason_name']); ?></h4>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $admission['discharge_status'])); ?>">
                                            <?php echo $is_current ? 'Currently Admitted' : htmlspecialchars($admission['discharge_status']); ?>
                                        </span>
                                    </div>
                                    <div class="admission-duration">
                                        <span class="duration-text"><?php echo $admission['stay_duration']; ?> days</span>
                                    </div>
                                </div>
                                
                                <div class="admission-details">
                                    <div class="detail-row">
                                        <div class="detail-col">
                                            <strong>📅 Admission:</strong>
                                            <span><?php echo $admission['formatted_admission_date']; ?></span>
                                        </div>
                                        <div class="detail-col">
                                            <strong>📅 Discharge:</strong>
                                            <span><?php echo $admission['formatted_discharge_date'] ?: '<em>Still admitted</em>'; ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($admission['doctor_name']): ?>
                                        <div class="detail-row">
                                            <div class="detail-col">
                                                <strong>👨‍⚕️ Attending Doctor:</strong>
                                                <span>
                                                    <?php 
                                                    if ($admission['specialization']) {
                                                        echo htmlspecialchars($admission['specialization']) . ' - ';
                                                    }
                                                    echo 'Dr. ' . htmlspecialchars($admission['doctor_name']);
                                                    ?>
                                                </span>
                                            </div>
                                            <?php if ($admission['nursing_name']): ?>
                                                <div class="detail-col">
                                                    <strong>👩‍⚕️ Nursing Officer:</strong>
                                                    <span><?php echo htmlspecialchars($admission['nursing_name']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($admission['notes'])): ?>
                                        <div class="detail-row">
                                            <div class="detail-col full-width">
                                                <strong>📝 Notes:</strong>
                                                <div class="notes-content"><?php echo nl2br(htmlspecialchars($admission['notes'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-history">
                <div class="no-history-content">
                    <h4>📋 No Admission History</h4>
                    <p>This patient has no recorded admissions in the system.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Investigation History -->
<div class="history-section">
    <div class="section-header">
        <div class="section-title">
            <h3>🔬 Investigation History</h3>
            <div class="record-count"><?php echo $investigations_result->num_rows; ?> investigations</div>
        </div>
    </div>
    
    <div class="timeline-container">
        <?php if ($investigations_result->num_rows > 0): ?>
            <div class="timeline">
                <?php while ($investigation = $investigations_result->fetch_assoc()): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker investigation-marker">🔬</div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h4><?php echo htmlspecialchars($investigation['investigation_name']); ?></h4>
                                <div class="timeline-date">
                                    <?php echo $investigation['formatted_ordered_date']; ?>
                                    <?php if ($investigation['ordered_time']): ?>
                                        at <?php echo date('g:i A', strtotime($investigation['ordered_time'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="investigation-details">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="label">Investigation Type:</span>
                                        <span class="value"><?php echo htmlspecialchars($investigation['investigation_type']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Admission:</span>
                                        <span class="value"><?php echo htmlspecialchars($investigation['admission_reason'] ?? 'Unknown'); ?> (#<?php echo str_pad($investigation['admission_id'], 4, '0', STR_PAD_LEFT); ?>)</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Ordered By:</span>
                                        <span class="value"><?php echo htmlspecialchars($investigation['ordered_by_name'] ?? 'Unknown'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Status:</span>
                                        <span class="value status-badge status-<?php echo strtolower(str_replace(' ', '-', $investigation['result_status'])); ?>">
                                            <?php echo htmlspecialchars($investigation['result_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($investigation['result_values']): ?>
                                    <div class="investigation-results">
                                        <div class="results-header">📊 Results:</div>
                                        <div class="results-content">
                                            <div class="result-value"><?php echo htmlspecialchars($investigation['result_values']); ?></div>
                                            <?php if ($investigation['normal_range']): ?>
                                                <div class="normal-range">Normal Range: <?php echo htmlspecialchars($investigation['normal_range']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($investigation['interpretation']): ?>
                                                <div class="interpretation">
                                                    <strong>Interpretation:</strong> <?php echo htmlspecialchars($investigation['interpretation']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($investigation['result_date']): ?>
                                                <div class="result-date">Result Date: <?php echo $investigation['formatted_result_date']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($investigation['remarks']): ?>
                                    <div class="investigation-notes">
                                        <div class="notes-header">📝 Remarks:</div>
                                        <div class="notes-content"><?php echo htmlspecialchars($investigation['remarks']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-history">
                <div class="no-history-content">
                    <h4>🔬 No Investigation History</h4>
                    <p>This patient has no recorded investigations in the system.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Medicine History -->
<div class="history-section">
    <div class="section-header">
        <div class="section-title">
            <h3>💊 Medicine History</h3>
            <div class="record-count"><?php echo $medicines_result->num_rows; ?> prescriptions</div>
        </div>
    </div>
    
    <div class="timeline-container">
        <?php if ($medicines_result->num_rows > 0): ?>
            <div class="timeline">
                <?php while ($medicine = $medicines_result->fetch_assoc()): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker medicine-marker">💊</div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h4><?php echo htmlspecialchars($medicine['medicine_name']); ?></h4>
                                <div class="timeline-date">
                                    <?php echo $medicine['formatted_start_date']; ?>
                                    <?php if ($medicine['start_time']): ?>
                                        at <?php echo date('g:i A', strtotime($medicine['start_time'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="medicine-details">
                                <div class="detail-grid">
                                    <?php if ($medicine['generic_name']): ?>
                                        <div class="detail-item">
                                            <span class="label">Generic Name:</span>
                                            <span class="value"><?php echo htmlspecialchars($medicine['generic_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-item">
                                        <span class="label">Dosage:</span>
                                        <span class="value"><?php echo htmlspecialchars($medicine['dosage']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Route:</span>
                                        <span class="value"><?php echo htmlspecialchars($medicine['route']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Frequency:</span>
                                        <span class="value"><?php echo htmlspecialchars($medicine['frequency']); ?></span>
                                    </div>
                                    <?php if ($medicine['duration']): ?>
                                        <div class="detail-item">
                                            <span class="label">Duration:</span>
                                            <span class="value"><?php echo htmlspecialchars($medicine['duration']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-item">
                                        <span class="label">Admission:</span>
                                        <span class="value"><?php echo htmlspecialchars($medicine['admission_reason'] ?? 'Unknown'); ?> (#<?php echo str_pad($medicine['admission_id'], 4, '0', STR_PAD_LEFT); ?>)</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Prescribed By:</span>
                                        <span class="value"><?php echo htmlspecialchars($medicine['prescribed_by_name'] ?? 'Unknown'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Status:</span>
                                        <span class="value status-badge status-<?php echo strtolower($medicine['status']); ?>">
                                            <?php echo htmlspecialchars($medicine['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($medicine['end_date']): ?>
                                    <div class="medicine-duration">
                                        <div class="duration-info">
                                            <span class="duration-label">Treatment Period:</span>
                                            <span class="duration-value">
                                                <?php echo $medicine['formatted_start_date']; ?> to <?php echo $medicine['formatted_end_date']; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($medicine['indication']): ?>
                                    <div class="medicine-indication">
                                        <div class="indication-header">🎯 Indication:</div>
                                        <div class="indication-content"><?php echo htmlspecialchars($medicine['indication']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($medicine['instructions']): ?>
                                    <div class="medicine-instructions">
                                        <div class="instructions-header">📋 Instructions:</div>
                                        <div class="instructions-content"><?php echo htmlspecialchars($medicine['instructions']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($medicine['side_effects']): ?>
                                    <div class="medicine-side-effects">
                                        <div class="side-effects-header">⚠️ Side Effects:</div>
                                        <div class="side-effects-content"><?php echo htmlspecialchars($medicine['side_effects']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-history">
                <div class="no-history-content">
                    <h4>💊 No Medicine History</h4>
                    <p>This patient has no recorded prescriptions in the system.</p>
                </div>
            </div>
        <?php endif; ?>
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

.main-title {
    font-size: 2.5rem;
    margin: 0;
    font-weight: 700;
}

.subtitle {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

/* Patient Info Card */
.patient-info-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.card-header h3 {
    margin: 0;
    color: #495057;
}

.export-actions {
    display: flex;
    gap: 0.75rem;
}

.patient-details {
    padding: 2rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-item .label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.detail-item .value {
    font-size: 1rem;
    color: #212529;
}

/* History Section */
.history-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.history-header h3 {
    margin: 0;
    color: #495057;
}

.history-summary {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.total-admissions {
    font-size: 2rem;
    font-weight: 700;
    color: #3498db;
}

.summary-label {
    font-size: 0.9rem;
    color: #7f8c8d;
}

/* Timeline */
.timeline {
    padding: 2rem;
    position: relative;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 3rem;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(to bottom, #3498db, #e9ecef);
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
    padding-left: 4rem;
}

.timeline-marker {
    position: absolute;
    left: 1.5rem;
    top: 0.5rem;
    width: 3rem;
    height: 3rem;
    background: white;
    border: 3px solid #3498db;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.timeline-item.current .timeline-marker {
    border-color: #e74c3c;
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
}

.admission-number {
    font-weight: 700;
    font-size: 0.9rem;
    color: #3498db;
}

.timeline-item.current .admission-number {
    color: #e74c3c;
}

.timeline-content {
    margin-left: 1rem;
}

.admission-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
    border-left: 4px solid #3498db;
}

.timeline-item.current .admission-card {
    border-left-color: #e74c3c;
    background: #fff5f5;
}

.admission-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.admission-title h4 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.complete {
    background: #d4edda;
    color: #155724;
}

.status-badge.incomplete {
    background: #fff3cd;
    color: #856404;
}

.status-badge.currently-admitted {
    background: #f8d7da;
    color: #721c24;
}

.duration-text {
    font-weight: 600;
    color: #7f8c8d;
    font-size: 0.9rem;
}

.admission-details {
    space-y: 1rem;
}

.detail-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 1rem;
}

.detail-col.full-width {
    grid-column: 1 / -1;
}

.detail-col strong {
    display: block;
    margin-bottom: 0.25rem;
    color: #495057;
    font-size: 0.9rem;
}

.notes-content {
    background: white;
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    margin-top: 0.5rem;
    line-height: 1.6;
}

.no-history {
    padding: 4rem 2rem;
    text-align: center;
}

.no-history-content h4 {
    color: #7f8c8d;
    margin-bottom: 1rem;
}

.no-history-content p {
    color: #adb5bd;
}

/* Button Styles */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    text-align: center;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    margin: 0;
    font-size: 0.9rem;
}

.btn-outline {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
}

.btn-success {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
    box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
}

.btn-info {
    background: linear-gradient(135deg, #1abc9c, #16a085);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.btn-outline:hover {
    background: rgba(255,255,255,0.3);
}

/* Badge Styles */
.badge {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.badge-blue {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    color: #1976d2;
}

.badge-purple {
    background: linear-gradient(135deg, #f3e5f5, #e1bee7);
    color: #7b1fa2;
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
    }
    
    .main-title {
        font-size: 2rem;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .timeline::before {
        left: 1.5rem;
    }
    
    .timeline-item {
        padding-left: 2.5rem;
    }
    
    .timeline-marker {
        left: 0;
        width: 2rem;
        height: 2rem;
    }
}

/* Investigation and Medicine History Styles */
.investigation-marker, .medicine-marker {
    border-color: #9b59b6;
    box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
    font-size: 1.2rem;
}

.medicine-marker {
    border-color: #e67e22;
    box-shadow: 0 4px 12px rgba(230, 126, 34, 0.3);
}

.investigation-details, .medicine-details {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
    border-left: 4px solid #9b59b6;
    margin-top: 1rem;
}

.medicine-details {
    border-left-color: #e67e22;
}

.investigation-results {
    background: #e8f6f3;
    border-radius: 6px;
    padding: 1rem;
    margin-top: 1rem;
    border-left: 3px solid #16a085;
}

.results-header, .indication-header, .instructions-header, .side-effects-header, .notes-header {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.results-content, .indication-content, .instructions-content, .side-effects-content, .notes-content {
    color: #34495e;
    line-height: 1.5;
}

.result-value {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.normal-range {
    font-size: 0.85rem;
    color: #7f8c8d;
    font-style: italic;
}

.interpretation {
    margin-top: 0.5rem;
    color: #2c3e50;
}

.result-date {
    font-size: 0.85rem;
    color: #95a5a6;
    margin-top: 0.5rem;
}

.medicine-indication, .medicine-instructions, .medicine-side-effects, .investigation-notes {
    background: #fff;
    border-radius: 6px;
    padding: 1rem;
    margin-top: 1rem;
    border: 1px solid #ecf0f1;
}

.medicine-side-effects {
    background: #fef9e7;
    border-color: #f39c12;
}

.medicine-duration {
    background: #e8f5e8;
    border-radius: 6px;
    padding: 1rem;
    margin-top: 1rem;
    border-left: 3px solid #27ae60;
}

.duration-label {
    font-weight: 600;
    color: #2c3e50;
    margin-right: 0.5rem;
}

.duration-value {
    color: #27ae60;
    font-weight: 500;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-completed {
    background: #cce5ff;
    color: #004085;
}

.status-discontinued {
    background: #f8d7da;
    color: #721c24;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-in-progress {
    background: #cce5ff;
    color: #004085;
}

@media print {
    .action-buttons, .export-actions { display: none !important; }
    .header-section { background: white !important; color: black !important; }
    .timeline::before { background: #ddd !important; }
}
</style>

<?php include '../../includes/footer.php'; ?>