<?php
include '../../config/db.php';
include '../../includes/header.php';

// Handle form submission and build query
$where_conditions = [];
$params = [];
$param_types = "";

// Get filter values
$filter_name = $_GET['filter_name'] ?? '';
$filter_nic = $_GET['filter_nic'] ?? '';
$filter_gender = $_GET['filter_gender'] ?? '';
$filter_blood_group = $_GET['filter_blood_group'] ?? '';
$filter_age_min = $_GET['filter_age_min'] ?? '';
$filter_age_max = $_GET['filter_age_max'] ?? '';
$filter_nursing_officer = $_GET['filter_nursing_officer'] ?? '';
$export = $_GET['export'] ?? '';

// Build WHERE conditions
if (!empty($filter_name)) {
    $where_conditions[] = "(calling_name LIKE ? OR full_name LIKE ?)";
    $params[] = "%$filter_name%";
    $params[] = "%$filter_name%";
    $param_types .= "ss";
}

if (!empty($filter_nic)) {
    $where_conditions[] = "nic LIKE ?";
    $params[] = "%$filter_nic%";
    $param_types .= "s";
}

if (!empty($filter_gender)) {
    $where_conditions[] = "sex = ?";
    $params[] = $filter_gender;
    $param_types .= "s";
}

if (!empty($filter_blood_group)) {
    $where_conditions[] = "blood_group = ?";
    $params[] = $filter_blood_group;
    $param_types .= "s";
}

if (!empty($filter_age_min)) {
    $where_conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?";
    $params[] = $filter_age_min;
    $param_types .= "i";
}

if (!empty($filter_age_max)) {
    $where_conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?";
    $params[] = $filter_age_max;
    $param_types .= "i";
}

if (!empty($filter_nursing_officer)) {
    $where_conditions[] = "p.assigned_nursing_officer = ?";
    $params[] = $filter_nursing_officer;
    $param_types .= "i";
}

// Build the main query
$base_query = "SELECT p.*, 
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               no.nursing_name,
               (SELECT COUNT(*) FROM ward_admissions wa WHERE wa.patient_id = p.patient_id) as admission_count,
               (SELECT MAX(wa.admission_date) FROM ward_admissions wa WHERE wa.patient_id = p.patient_id) as last_admission
               FROM patients p
               LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id";

if (!empty($where_conditions)) {
    $base_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$base_query .= " ORDER BY p.calling_name ASC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($base_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($base_query);
}

// Get nursing officers for filter dropdown
$nursing_officers = $conn->query("SELECT nursing_id, nursing_name FROM nursing_officers ORDER BY nursing_name ASC");

// Handle CSV export
if ($export === 'csv' && $result->num_rows > 0) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="patient_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Calling Name', 'Full Name', 'NIC', 'Hospital Number', 'Clinic Number',
        'Date of Birth', 'Age', 'Gender', 'Blood Group', 'Contact Number',
        'Address', 'Guardian Name', 'Guardian Contact', 'Nursing Officer',
        'Total Admissions', 'Last Admission'
    ]);
    
    // Reset result pointer
    if (!empty($params)) {
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($base_query);
    }
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['calling_name'],
            $row['full_name'],
            $row['nic'],
            $row['hospital_number'],
            $row['clinic_number'],
            $row['date_of_birth'],
            $row['age'],
            $row['sex'],
            $row['blood_group'],
            $row['contact_number'],
            $row['address'],
            $row['guardian_name'],
            $row['guardian_contact_number'],
            $row['nursing_name'],
            $row['admission_count'],
            $row['last_admission']
        ]);
    }
    
    fclose($output);
    exit;
}

// Reset result for display if export was not requested
if ($export !== 'csv') {
    if (!empty($params)) {
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($base_query);
    }
}
?>

<div class="container">
    <div class="header">
        <div>
            <h1>🤒 Patient Details Report</h1>
            <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Comprehensive patient information with advanced filtering</p>
        </div>
        <div>
            <a href="report_list.php" class="btn btn-secondary">← Back to Reports</a>
            <a href="../../index.php" class="btn btn-secondary">🏠 Dashboard</a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card">
        <h3>🔍 Filter Options</h3>
        <form method="GET" action="" id="filterForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                
                <div class="form-group">
                    <label for="filter_name">👤 Patient Name:</label>
                    <input type="text" id="filter_name" name="filter_name" 
                           value="<?php echo htmlspecialchars($filter_name); ?>" 
                           placeholder="Search by calling name or full name">
                </div>
                
                <div class="form-group">
                    <label for="filter_nic">🆔 NIC Number:</label>
                    <input type="text" id="filter_nic" name="filter_nic" 
                           value="<?php echo htmlspecialchars($filter_nic); ?>" 
                           placeholder="Enter NIC number">
                </div>
                
                <div class="form-group">
                    <label for="filter_gender">⚤ Gender:</label>
                    <select id="filter_gender" name="filter_gender">
                        <option value="">All Genders</option>
                        <option value="Male" <?php echo $filter_gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $filter_gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_blood_group">🩸 Blood Group:</label>
                    <select id="filter_blood_group" name="filter_blood_group">
                        <option value="">All Blood Groups</option>
                        <?php
                        $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        foreach ($blood_groups as $bg) {
                            $selected = $filter_blood_group === $bg ? 'selected' : '';
                            echo "<option value='$bg' $selected>$bg</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_age_min">📅 Min Age:</label>
                    <input type="number" id="filter_age_min" name="filter_age_min" 
                           value="<?php echo htmlspecialchars($filter_age_min); ?>" 
                           placeholder="Minimum age" min="0" max="120">
                </div>
                
                <div class="form-group">
                    <label for="filter_age_max">📅 Max Age:</label>
                    <input type="number" id="filter_age_max" name="filter_age_max" 
                           value="<?php echo htmlspecialchars($filter_age_max); ?>" 
                           placeholder="Maximum age" min="0" max="120">
                </div>
                
                <div class="form-group">
                    <label for="filter_nursing_officer">🏥 Nursing Officer:</label>
                    <select id="filter_nursing_officer" name="filter_nursing_officer">
                        <option value="">All Nursing Officers</option>
                        <?php while ($no = $nursing_officers->fetch_assoc()): ?>
                            <option value="<?php echo $no['nursing_id']; ?>" 
                                    <?php echo $filter_nursing_officer == $no['nursing_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($no['nursing_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: center;">
                <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                <a href="patient_report.php" class="btn btn-secondary">🔄 Clear Filters</a>
                <?php if ($result->num_rows > 0): ?>
                    <button type="submit" name="export" value="csv" class="btn btn-success">📊 Export CSV</button>
                    <button type="button" onclick="window.print()" class="btn btn-info">🖨️ Print Report</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3>📊 Report Results</h3>
                <p style="color: #666; margin: 0;">Found <?php echo $result->num_rows; ?> patient(s) matching your criteria</p>
            </div>
            <div style="text-align: right; color: #666; font-size: 0.9rem;">
                Generated on: <?php echo date('F j, Y \a\t g:i A'); ?>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <?php if ($result->num_rows > 0): ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table" id="patientsTable">
                    <thead>
                        <tr>
                            <th>👤 Patient Name</th>
                            <th>🆔 NIC</th>
                            <th>🏥 Hospital No.</th>
                            <th>📅 Age</th>
                            <th>⚤ Gender</th>
                            <th>🩸 Blood Group</th>
                            <th>📞 Contact</th>
                            <th>🏥 Nursing Officer</th>
                            <th>📊 Admissions</th>
                            <th>📅 Last Visit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($patient = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($patient['calling_name']); ?></strong>
                                        <br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($patient['full_name']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($patient['nic']); ?></td>
                                <td><?php echo htmlspecialchars($patient['hospital_number'] ?: 'Not assigned'); ?></td>
                                <td><?php echo $patient['age']; ?> years</td>
                                <td>
                                    <span class="badge <?php echo $patient['sex'] === 'Male' ? 'badge-blue' : 'badge-pink'; ?>">
                                        <?php echo htmlspecialchars($patient['sex']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-red"><?php echo htmlspecialchars($patient['blood_group']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($patient['contact_number']); ?></td>
                                <td><?php echo htmlspecialchars($patient['nursing_name'] ?: 'Not assigned'); ?></td>
                                <td>
                                    <span class="badge badge-green"><?php echo $patient['admission_count']; ?></span>
                                </td>
                                <td><?php echo $patient['last_admission'] ? date('M j, Y', strtotime($patient['last_admission'])) : 'Never'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div style="text-align: center; padding: 3rem; color: #666;">
                <h3>📋 No Patients Found</h3>
                <p>No patients match your current filter criteria. Try adjusting your filters or clearing them to see all patients.</p>
                <a href="patient_report.php" class="btn btn-primary" style="margin-top: 1rem;">🔄 Show All Patients</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    display: inline-block;
    text-align: center;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    margin: 0 0.25rem;
}

.btn-primary { background: #007bff; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-info { background: #17a2b8; color: white; }

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.table-responsive {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table th,
.table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
    vertical-align: top;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.table tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-blue { background: #e3f2fd; color: #1976d2; }
.badge-pink { background: #fce4ec; color: #c2185b; }
.badge-red { background: #ffebee; color: #d32f2f; }
.badge-green { background: #e8f5e8; color: #388e3c; }

@media print {
    .header, .card:first-child { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php include '../../includes/footer.php'; ?>