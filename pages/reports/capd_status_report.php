<?php
include '../../config/db.php';
include '../../includes/header.php';

// Handle form submission and build query
$where_conditions = [];

// Get filter values
$filter_patient_name = $_GET['filter_patient_name'] ?? '';
$filter_clinic_number = $_GET['filter_clinic_number'] ?? '';
$filter_age_from = $_GET['filter_age_from'] ?? '';
$filter_age_to = $_GET['filter_age_to'] ?? '';
$filter_catheter_date_from = $_GET['filter_catheter_date_from'] ?? '';
$filter_catheter_date_to = $_GET['filter_catheter_date_to'] ?? '';
$filter_capd_start_from = $_GET['filter_capd_start_from'] ?? '';
$filter_capd_start_to = $_GET['filter_capd_start_to'] ?? '';
$export = $_GET['export'] ?? '';

// Build WHERE conditions
if (!empty($filter_patient_name)) {
    $escaped_name = $conn->real_escape_string($filter_patient_name);
    $where_conditions[] = "(p.calling_name LIKE '%$escaped_name%' OR p.full_name LIKE '%$escaped_name%')";
}

if (!empty($filter_clinic_number)) {
    $escaped_clinic = $conn->real_escape_string($filter_clinic_number);
    $where_conditions[] = "p.clinic_number LIKE '%$escaped_clinic%'";
}

if (!empty($filter_age_from)) {
    $escaped_age_from = $conn->real_escape_string($filter_age_from);
    $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) >= $escaped_age_from";
}

if (!empty($filter_age_to)) {
    $escaped_age_to = $conn->real_escape_string($filter_age_to);
    $where_conditions[] = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) <= $escaped_age_to";
}

if (!empty($filter_catheter_date_from)) {
    $escaped_catheter_from = $conn->real_escape_string($filter_catheter_date_from);
    $where_conditions[] = "cs.catheter_insertion_date >= '$escaped_catheter_from'";
}

if (!empty($filter_catheter_date_to)) {
    $escaped_catheter_to = $conn->real_escape_string($filter_catheter_date_to);
    $where_conditions[] = "cs.catheter_insertion_date <= '$escaped_catheter_to'";
}

if (!empty($filter_capd_start_from)) {
    $escaped_capd_from = $conn->real_escape_string($filter_capd_start_from);
    $where_conditions[] = "cs.capd_start_date >= '$escaped_capd_from'";
}

if (!empty($filter_capd_start_to)) {
    $escaped_capd_to = $conn->real_escape_string($filter_capd_start_to);
    $where_conditions[] = "cs.capd_start_date <= '$escaped_capd_to'";
}

// Build the main query - Get LATEST CAPD record per patient
$base_query = "SELECT p.calling_name, p.full_name, p.clinic_number, p.hospital_number, p.nic, p.date_of_birth,
               cs.capd_id, cs.catheter_insertion_date, cs.capd_start_date,
               d.doctor_name as surgeon_doctor, d.specialization as surgeon_specialization,
               no.nursing_name as assigned_nursing_officer,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
               FROM patients p
               LEFT JOIN (
                   SELECT * FROM capd_status cs1
                   WHERE cs1.capd_id = (
                       SELECT MAX(cs2.capd_id) FROM capd_status cs2 
                       WHERE cs2.patient_id = cs1.patient_id
                   )
               ) cs ON p.patient_id = cs.patient_id
               LEFT JOIN doctors d ON cs.surgeon_doctor_id = d.doctor_id
               LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id";

if (!empty($where_conditions)) {
    $base_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$base_query .= " ORDER BY p.calling_name ASC";

// Execute query
$result = $conn->query($base_query);

// Handle CSV export
if ($export === 'csv' && $result->num_rows > 0) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="capd_status_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Patient Name', 'Full Name', 'Clinic Number', 'Hospital Number', 'NIC', 'Age', 'Catheter Insertion Date', 'Surgeon Doctor', 'CAPD Process Start Date', 'Assigned Nursing Officer', 'CAPD Status'
    ]);
    
    // Reset result pointer
    $result = $conn->query($base_query);
    
    while ($row = $result->fetch_assoc()) {
        // Determine CAPD Status for CSV
        $capd_status = '';
        
        // Check if catheter insertion date is valid and not empty
        $has_valid_catheter = !empty($row['catheter_insertion_date']) && $row['catheter_insertion_date'] !== '0000-00-00';
        // Check if CAPD start date is valid and not empty
        $has_valid_capd_start = !empty($row['capd_start_date']) && $row['capd_start_date'] !== '0000-00-00';
        
        if ($has_valid_catheter && $has_valid_capd_start) {
            $capd_status = 'CAPD Active';
        } elseif ($has_valid_catheter && !$has_valid_capd_start) {
            $capd_status = 'Waiting CAPD';
        } else {
            $capd_status = 'Waiting Catheter';
        }
        
        // Format doctor name with specialization for CSV
        $doctor_with_specialization = '';
        if (!empty($row['surgeon_doctor'])) {
            if (!empty($row['surgeon_specialization'])) {
                $doctor_with_specialization = $row['surgeon_specialization'] . ' - Dr. ' . $row['surgeon_doctor'];
            } else {
                $doctor_with_specialization = 'Dr. ' . $row['surgeon_doctor'];
            }
        }
        
        // Format CAPD start date for CSV
        $capd_start_for_csv = '';
        if (!empty($row['capd_start_date']) && $row['capd_start_date'] !== '0000-00-00') {
            $capd_start_for_csv = date('M j, Y', strtotime($row['capd_start_date']));
        }
        
        fputcsv($output, [
            $row['calling_name'],
            $row['full_name'],
            $row['clinic_number'],
            $row['hospital_number'],
            $row['nic'],
            $row['age'],
            $row['catheter_insertion_date'],
            $doctor_with_specialization,
            $capd_start_for_csv,
            $row['assigned_nursing_officer'] ?? '',
            $capd_status
        ]);
    }
    
    fclose($output);
    exit;
}

// Reset result for display if export was not requested
if ($export !== 'csv') {
    $result = $conn->query($base_query);
}
?>

<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-content">
            <div class="title-section">
                <h1 class="main-title">🩺 CAPD Status Report</h1>
                <p class="subtitle">Comprehensive CAPD patient status and treatment timeline</p>
            </div>
            <div class="action-buttons">
                <a href="capd_cleanup.php" class="btn btn-outline" title="Clean up invalid dates">🔧 Data Cleanup</a>
                <a href="report_list.php" class="btn btn-outline">← Back to Reports</a>
                <a href="../../index.php" class="btn btn-outline">🏠 Dashboard</a>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
        <div class="filter-header" onclick="toggleFilters()">
            <h3>🔍 Filter Options</h3>
            <span class="toggle-icon" id="filterToggle">▼</span>
        </div>
        <form method="GET" action="" id="filterForm">
            <div class="filter-content" id="filterContent">
                <div class="filter-grid">
                
                <div class="form-group">
                    <label for="filter_patient_name">👤 Patient Name:</label>
                    <div class="search-container" style="position: relative;">
                        <input type="text" id="filter_patient_name" name="filter_patient_name" 
                               value="<?php echo htmlspecialchars($filter_patient_name); ?>" 
                               placeholder="🔍 Search by patient name, NIC, Hospital Number, Clinic Number..."
                               autocomplete="off">
                        <div class="search-results" id="patient_name_search_results" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="filter_clinic_number">🏥 Clinic Number:</label>
                    <input type="text" id="filter_clinic_number" name="filter_clinic_number" 
                           value="<?php echo htmlspecialchars($filter_clinic_number); ?>" 
                           placeholder="Enter clinic number">
                </div>
                
                <div class="form-group">
                    <label for="filter_age_from">🎂 Age From:</label>
                    <input type="number" id="filter_age_from" name="filter_age_from" 
                           value="<?php echo htmlspecialchars($filter_age_from); ?>" 
                           placeholder="Min age" min="0" max="120">
                </div>
                
                <div class="form-group">
                    <label for="filter_age_to">🎂 Age To:</label>
                    <input type="number" id="filter_age_to" name="filter_age_to" 
                           value="<?php echo htmlspecialchars($filter_age_to); ?>" 
                           placeholder="Max age" min="0" max="120">
                </div>
                
                <div class="form-group">
                    <label for="filter_catheter_date_from">📅 Catheter Date From:</label>
                    <input type="date" id="filter_catheter_date_from" name="filter_catheter_date_from" 
                           value="<?php echo htmlspecialchars($filter_catheter_date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label for="filter_catheter_date_to">📅 Catheter Date To:</label>
                    <input type="date" id="filter_catheter_date_to" name="filter_catheter_date_to" 
                           value="<?php echo htmlspecialchars($filter_catheter_date_to); ?>">
                </div>
                
                <div class="form-group">
                    <label for="filter_capd_start_from">📅 CAPD Start From:</label>
                    <input type="date" id="filter_capd_start_from" name="filter_capd_start_from" 
                           value="<?php echo htmlspecialchars($filter_capd_start_from); ?>">
                </div>
                
                <div class="form-group">
                    <label for="filter_capd_start_to">📅 CAPD Start To:</label>
                    <input type="date" id="filter_capd_start_to" name="filter_capd_start_to" 
                           value="<?php echo htmlspecialchars($filter_capd_start_to); ?>">
                </div>
            </div>
                <div class="filter-actions">
                    <div class="primary-actions">
                        <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                        <a href="capd_status_report.php" class="btn btn-secondary">🔄 Clear Filters</a>
                    </div>
                    <?php if ($result->num_rows > 0): ?>
                        <div class="export-actions">
                            <button type="submit" name="export" value="csv" class="btn btn-success">📊 Export CSV</button>
                            <button type="button" onclick="window.print()" class="btn btn-info">🖨️ Print Report</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <div class="results-summary">
        <div class="summary-content">
            <div class="results-info">
                <h3 class="results-title">📊 Report Results</h3>
                <div class="results-count">
                    <span class="count-number"><?php echo $result->num_rows; ?></span>
                    <span class="count-label">CAPD patient(s) found</span>
                </div>
            </div>
            <div class="generation-info">
                <div class="generated-label">Generated on:</div>
                <div class="generated-date"><?php echo date('F j, Y \a\t g:i A'); ?></div>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <?php if ($result->num_rows > 0): ?>
        <div class="table-card">
            <div class="table-header">
                <h3>👥 Patient Details</h3>
                <div class="table-actions">
                    <button class="btn btn-sm btn-dark" onclick="toggleTableView()">📋 Compact View</button>
                </div>
            </div>
            <div class="table-container">
                <table class="modern-table" id="capdTable">
                    <thead>
                        <tr>
                            <th>👤 Patient Name</th>
                            <th>🏥 Clinic Number</th>
                            <th>🎂 Age</th>
                            <th>🩺 Catheter Insertion</th>
                            <th>📅 CAPD Process Start</th>
                            <th>📊 CAPD Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($patient = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($patient['calling_name']); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($patient['full_name']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-blue"><?php echo htmlspecialchars($patient['clinic_number'] ?: 'Not assigned'); ?></span>
                                    <br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($patient['hospital_number'] ?: 'Not assigned'); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-purple"><?php echo $patient['age']; ?> years</span>
                                    <br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($patient['nic']); ?></small>
                                </td>
                                <td>
                                    <?php if ($patient['catheter_insertion_date']): ?>
                                        <div>
                                            <strong><?php echo date('M j, Y', strtotime($patient['catheter_insertion_date'])); ?></strong>
                                            <?php if ($patient['surgeon_doctor']): ?>
                                                <br>
                                                <small style="color: #666;">
                                                    <?php 
                                                    if (!empty($patient['surgeon_specialization'])) {
                                                        echo htmlspecialchars($patient['surgeon_specialization']) . ' - ';
                                                    }
                                                    ?>
                                                    Dr. <?php echo htmlspecialchars($patient['surgeon_doctor']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">Not recorded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($patient['capd_start_date']) && $patient['capd_start_date'] !== '0000-00-00'): ?>
                                        <div>
                                            <strong><?php echo date('M j, Y', strtotime($patient['capd_start_date'])); ?></strong>
                                            <?php if ($patient['assigned_nursing_officer']): ?>
                                                <br>
                                                <small style="color: #666;">
                                                    <?php echo htmlspecialchars($patient['assigned_nursing_officer']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">Not started</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    // Check if dates are valid and not empty
                                    $has_valid_catheter = !empty($patient['catheter_insertion_date']) && $patient['catheter_insertion_date'] !== '0000-00-00';
                                    $has_valid_capd_start = !empty($patient['capd_start_date']) && $patient['capd_start_date'] !== '0000-00-00';
                                    
                                    if ($has_valid_catheter && $has_valid_capd_start) {
                                        echo '<span class="badge badge-green">CAPD Active</span>';
                                    } elseif ($has_valid_catheter && !$has_valid_capd_start) {
                                        echo '<span class="badge badge-orange">Waiting CAPD</span>';
                                    } else {
                                        echo '<span class="badge badge-red">Waiting Catheter</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Statistics Summary -->
        <div class="card">
            <h3>📈 CAPD Statistics</h3>
            <?php
            // Reset result for statistics
            $stats_result = $conn->query($base_query);
            
            $total_patients = $stats_result->num_rows;
            $patients_with_catheter = 0;
            $patients_started_capd = 0;
            $patients_waiting_capd = 0;
            $avg_age = 0;
            $total_age = 0;
            
            while ($stat = $stats_result->fetch_assoc()) {
                $has_valid_catheter = !empty($stat['catheter_insertion_date']) && $stat['catheter_insertion_date'] !== '0000-00-00';
                $has_valid_capd_start = !empty($stat['capd_start_date']) && $stat['capd_start_date'] !== '0000-00-00';
                
                if ($has_valid_catheter) {
                    $patients_with_catheter++;
                }
                if ($has_valid_capd_start) {
                    $patients_started_capd++;
                }
                if ($has_valid_catheter && !$has_valid_capd_start) {
                    $patients_waiting_capd++;
                }
                $total_age += $stat['age'];
            }
            
            $avg_age = $total_patients > 0 ? round($total_age / $total_patients, 1) : 0;
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #0277bd;">
                    <div style="font-size: 2rem; font-weight: bold; color: #0277bd;"><?php echo $total_patients; ?></div>
                    <div style="color: #666;">Total Patients</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #28a745;">
                    <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?php echo $patients_with_catheter; ?></div>
                    <div style="color: #666;">CAPD Active + Waiting</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #17a2b8;">
                    <div style="font-size: 2rem; font-weight: bold; color: #17a2b8;"><?php echo $patients_started_capd; ?></div>
                    <div style="color: #666;">CAPD Active</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #ff9800;">
                    <div style="font-size: 2rem; font-weight: bold; color: #ff9800;"><?php echo $patients_waiting_capd; ?></div>
                    <div style="color: #666;">Waiting CAPD</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #ffc107;">
                    <div style="font-size: 2rem; font-weight: bold; color: #e68900;"><?php echo $avg_age; ?></div>
                    <div style="color: #666;">Average Age</div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="card">
            <div style="text-align: center; padding: 3rem; color: #666;">
                <h3>📋 No CAPD Patients Found</h3>
                <p>No CAPD patients match your current filter criteria. Try adjusting your filters or clearing them to see all patients.</p>
                <a href="capd_status_report.php" class="btn btn-primary" style="margin-top: 1rem;">🔄 Show All CAPD Patients</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Modern Layout Styles */
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

/* Filter Card Styles */
.filter-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
    overflow: hidden;
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    cursor: pointer;
    border-bottom: 1px solid #dee2e6;
}

.filter-header h3 {
    margin: 0;
    color: #495057;
}

.toggle-icon {
    font-size: 1.2rem;
    transition: transform 0.3s ease;
    color: #6c757d;
}

.filter-content {
    padding: 2rem;
    transition: all 0.3s ease;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.filter-actions {
    margin-top: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.primary-actions, .export-actions {
    display: flex;
    gap: 0.75rem;
}

/* Results Summary */
.results-summary {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.summary-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.results-title {
    margin: 0 0 1rem 0;
    color: #2c3e50;
    font-size: 1.5rem;
}

.results-count {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
}

.count-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #3498db;
}

.count-label {
    color: #7f8c8d;
    font-weight: 500;
}

.generation-info {
    text-align: right;
}

.generated-label {
    font-size: 0.9rem;
    color: #7f8c8d;
    margin-bottom: 0.25rem;
}

.generated-date {
    font-weight: 600;
    color: #2c3e50;
}

/* Table Styles */
.table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
    overflow: hidden;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.table-header h3 {
    margin: 0;
    color: #495057;
}

.table-container {
    overflow-x: auto;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.modern-table th,
.modern-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: top;
}

.modern-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
    position: sticky;
    top: 0;
    z-index: 10;
}

.modern-table tr:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 50%);
}

/* Form Styles */
.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

.btn-primary { 
    background: linear-gradient(135deg, #3498db, #2980b9); 
    color: white; 
    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
}

.btn-secondary { 
    background: linear-gradient(135deg, #95a5a6, #7f8c8d); 
    color: white; 
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

.btn-outline {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
}

.btn-dark {
    background: linear-gradient(135deg, #495057, #343a40);
    color: white;
    border: 2px solid #495057;
    box-shadow: 0 2px 8px rgba(73, 80, 87, 0.3);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.btn-outline:hover {
    background: rgba(255,255,255,0.3);
}

.btn-dark:hover {
    background: linear-gradient(135deg, #6c757d, #495057);
    box-shadow: 0 4px 15px rgba(73, 80, 87, 0.4);
}

/* Badge Styles */
.badge {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-blue { 
    background: linear-gradient(135deg, #e3f2fd, #bbdefb); 
    color: #1976d2; 
    box-shadow: 0 2px 8px rgba(25, 118, 210, 0.2);
}

.badge-purple { 
    background: linear-gradient(135deg, #f3e5f5, #e1bee7); 
    color: #7b1fa2; 
    box-shadow: 0 2px 8px rgba(123, 31, 162, 0.2);
}

.badge-green { 
    background: linear-gradient(135deg, #e8f5e8, #c8e6c9); 
    color: #2e7d32; 
    box-shadow: 0 2px 8px rgba(46, 125, 50, 0.2);
}

.badge-orange { 
    background: linear-gradient(135deg, #fff3e0, #ffe0b2); 
    color: #f57c00; 
    box-shadow: 0 2px 8px rgba(245, 124, 0, 0.2);
}

.badge-red { 
    background: linear-gradient(135deg, #ffebee, #ffcdd2); 
    color: #d32f2f; 
    box-shadow: 0 2px 8px rgba(211, 47, 47, 0.2);
}

.badge-gray { 
    background: linear-gradient(135deg, #f5f5f5, #eeeeee); 
    color: #616161; 
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
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .primary-actions, .export-actions {
        justify-content: center;
    }
    
    .summary-content {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .modern-table {
        font-size: 0.8rem;
    }
    
    .modern-table th,
    .modern-table td {
        padding: 0.5rem;
    }
}

@media print {
    .filter-card, .action-buttons { display: none !important; }
    .header-section { background: white !important; color: black !important; }
    .table-card { box-shadow: none; border: 1px solid #ddd; }
    .modern-table { font-size: 0.8rem; }
}

/* Animation Classes */
.filter-content.collapsed {
    display: none;
}

.toggle-icon.rotated {
    transform: rotate(-90deg);
}

/* Loading Animation */
.loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Compact Table View */
.modern-table.compact th,
.modern-table.compact td {
    padding: 0.5rem;
    font-size: 0.8rem;
}

.modern-table.compact .badge {
    padding: 0.2rem 0.5rem;
    font-size: 0.7rem;
}
</style>

<script>
// Toggle filter collapse
function toggleFilters() {
    const content = document.getElementById('filterContent');
    const icon = document.getElementById('filterToggle');
    
    content.classList.toggle('collapsed');
    icon.classList.toggle('rotated');
    
    if (content.classList.contains('collapsed')) {
        icon.textContent = '▶';
    } else {
        icon.textContent = '▼';
    }
}

// Toggle table view
function toggleTableView() {
    const table = document.getElementById('capdTable');
    table.classList.toggle('compact');
    
    const button = event.target;
    if (table.classList.contains('compact')) {
        button.textContent = '📋 Full View';
    } else {
        button.textContent = '📋 Compact View';
    }
}

// Add loading animation on form submit
document.getElementById('filterForm').addEventListener('submit', function() {
    document.body.classList.add('loading');
});

// Initialize filters as collapsed on mobile
if (window.innerWidth <= 768) {
    document.addEventListener('DOMContentLoaded', function() {
        toggleFilters();
    });
}

// Patient name autocomplete functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('filter_patient_name');
    const searchResults = document.getElementById('patient_name_search_results');
    
    if (searchInput && searchResults) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(function() {
                console.log('Searching for:', searchTerm);
                fetch(`patient_search_ajax.php?search_term=${encodeURIComponent(searchTerm)}`)
                    .then(response => {
                        console.log('Response status:', response.status);
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(patients => {
                        console.log('Patients found:', patients);
                        if (patients.error) {
                            console.error('Server error:', patients.error);
                            if (searchResults) {
                                searchResults.innerHTML = '<div class="search-item" style="padding: 1rem; color: #f00; text-align: center;">Server error: ' + patients.error + '</div>';
                                searchResults.style.display = 'block';
                            }
                        } else if (searchResults) {
                            displayPatientSearchResults(patients);
                        } else {
                            console.error('Search results container not found');
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        if (searchResults) {
                            searchResults.innerHTML = '<div class="search-item" style="padding: 1rem; color: #f00; text-align: center;">Network error: ' + error.message + '</div>';
                            searchResults.style.display = 'block';
                        }
                    });
            }, 300);
        });
        
        function displayPatientSearchResults(patients) {
            if (patients.length === 0) {
                searchResults.innerHTML = '<div class="search-item" style="padding: 1rem; color: #666; text-align: center;">No patients found</div>';
            } else {
                searchResults.innerHTML = patients.map(patient => 
                    `<div class="search-item" onclick="selectPatientName('${patient.calling_name}')" style="padding: 0.75rem; border-bottom: 1px solid #eee; cursor: pointer; transition: background-color 0.2s;">
                        <div style="font-weight: 600; color: #2c3e50; margin-bottom: 0.25rem;">
                            ${patient.calling_name} <span style="font-weight: 400; color: #666;">(${patient.full_name})</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #888;">
                            NIC: ${patient.nic} | H#: ${patient.hospital_number || 'Not assigned'} | C#: ${patient.clinic_number || 'Not assigned'}
                        </div>
                    </div>`
                ).join('');
            }
            searchResults.style.display = 'block';
        }
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }
});

function selectPatientName(callingName) {
    document.getElementById('filter_patient_name').value = callingName;
    document.getElementById('patient_name_search_results').style.display = 'none';
}
</script>

<?php include '../../includes/footer.php'; ?>