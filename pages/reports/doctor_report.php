<?php
include '../../config/db.php';
include '../../includes/header.php';

// Handle form submission and build query
$where_conditions = [];
$params = [];
$param_types = "";

// Get filter values
$filter_name = $_GET['filter_name'] ?? '';
$filter_specialization = $_GET['filter_specialization'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$export = $_GET['export'] ?? '';

// Build WHERE conditions
if (!empty($filter_name)) {
    $where_conditions[] = "d.doctor_name LIKE ?";
    $params[] = "%$filter_name%";
    $param_types .= "s";
}

if (!empty($filter_specialization)) {
    $where_conditions[] = "d.specialization LIKE ?";
    $params[] = "%$filter_specialization%";
    $param_types .= "s";
}



if (!empty($filter_status)) {
    if ($filter_status === 'active') {
        $where_conditions[] = "u.user_id IS NOT NULL";
    } elseif ($filter_status === 'inactive') {
        $where_conditions[] = "u.user_id IS NULL";
    }
}

// Build the main query
$base_query = "SELECT d.*, u.username, u.user_role,
               (SELECT COUNT(*) FROM ward_admissions wa WHERE wa.attending_doctor_id = d.doctor_id) as patient_count
               FROM doctors d
               LEFT JOIN users u ON d.user_id = u.user_id";

if (!empty($where_conditions)) {
    $base_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$base_query .= " ORDER BY d.doctor_name ASC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($base_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($base_query);
}

// Get unique specializations for filter dropdown
$specializations = $conn->query("SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization ASC");

// Handle CSV export
if ($export === 'csv' && $result->num_rows > 0) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="doctor_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Doctor Name', 'Specialization', 'Contact Number', 'Username', 'Status', 'Current Patients'
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
            $row['doctor_name'],
            $row['specialization'],
            $row['contact_number'],
            $row['username'],
            $row['username'] ? 'Active' : 'Inactive',
            $row['patient_count']
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
            <h1>👨‍⚕️ Doctor Details Report</h1>
            <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Comprehensive doctor information with specialization filters</p>
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
                    <label for="filter_name">👨‍⚕️ Doctor Name:</label>
                    <input type="text" id="filter_name" name="filter_name" 
                           value="<?php echo htmlspecialchars($filter_name); ?>" 
                           placeholder="Search by doctor name">
                </div>
                
                <div class="form-group">
                    <label for="filter_specialization">🩺 Specialization:</label>
                    <select id="filter_specialization" name="filter_specialization">
                        <option value="">All Specializations</option>
                        <?php while ($spec = $specializations->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" 
                                    <?php echo $filter_specialization === $spec['specialization'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec['specialization']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_status">📊 Status:</label>
                    <select id="filter_status" name="filter_status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active (Has Login)</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive (No Login)</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: center;">
                <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                <a href="doctor_report.php" class="btn btn-secondary">🔄 Clear Filters</a>
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
                <p style="color: #666; margin: 0;">Found <?php echo $result->num_rows; ?> doctor(s) matching your criteria</p>
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
                <table class="table" id="doctorsTable">
                    <thead>
                        <tr>
                            <th>👨‍⚕️ Doctor Name</th>
                            <th>🩺 Specialization</th>
                            <th>📞 Contact</th>
                            <th>👤 Username</th>
                            <th>📊 Status</th>
                            <th>🤒 Patients</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doctor = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($doctor['doctor_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-purple">
                                        <?php echo htmlspecialchars($doctor['specialization'] ?: 'General'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($doctor['contact_number'] ?: 'Not provided'); ?></td>
                                <td>
                                    <?php if ($doctor['username']): ?>
                                        <span class="badge badge-green"><?php echo htmlspecialchars($doctor['username']); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">No Access</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($doctor['username']): ?>
                                        <span class="badge badge-green">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-red">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-orange"><?php echo $doctor['patient_count']; ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Statistics Summary -->
        <div class="card">
            <h3>📈 Summary Statistics</h3>
            <?php
            // Reset result for statistics
            if (!empty($params)) {
                $stmt->execute();
                $stats_result = $stmt->get_result();
            } else {
                $stats_result = $conn->query($base_query);
            }
            
            $total_doctors = $stats_result->num_rows;
            $active_doctors = 0;
            $specializations_count = [];
            $total_patients = 0;
            
            while ($stat = $stats_result->fetch_assoc()) {
                if ($stat['username']) $active_doctors++;
                
                $spec = $stat['specialization'] ?: 'General';
                $specializations_count[$spec] = ($specializations_count[$spec] ?? 0) + 1;
                
                $total_patients += $stat['patient_count'];
            }
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #28a745;">
                    <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?php echo $total_doctors; ?></div>
                    <div style="color: #666;">Total Doctors</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #007bff;">
                    <div style="font-size: 2rem; font-weight: bold; color: #007bff;"><?php echo $active_doctors; ?></div>
                    <div style="color: #666;">Active Doctors</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #ffc107;">
                    <div style="font-size: 2rem; font-weight: bold; color: #e68900;"><?php echo count($specializations_count); ?></div>
                    <div style="color: #666;">Specializations</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #dc3545;">
                    <div style="font-size: 2rem; font-weight: bold; color: #dc3545;"><?php echo $total_patients; ?></div>
                    <div style="color: #666;">Total Patients</div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="card">
            <div style="text-align: center; padding: 3rem; color: #666;">
                <h3>📋 No Doctors Found</h3>
                <p>No doctors match your current filter criteria. Try adjusting your filters or clearing them to see all doctors.</p>
                <a href="doctor_report.php" class="btn btn-primary" style="margin-top: 1rem;">🔄 Show All Doctors</a>
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
.badge-purple { background: #f3e5f5; color: #7b1fa2; }
.badge-green { background: #e8f5e8; color: #388e3c; }
.badge-red { background: #ffebee; color: #d32f2f; }
.badge-gray { background: #f5f5f5; color: #616161; }
.badge-orange { background: #fff3e0; color: #f57c00; }

@media print {
    .header, .card:first-child { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php include '../../includes/footer.php'; ?>