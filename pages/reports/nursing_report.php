<?php
include '../../config/db.php';
include '../../includes/header.php';

// Handle form submission and build query
$where_conditions = [];
$params = [];
$param_types = "";

// Get filter values
$filter_name = $_GET['filter_name'] ?? '';
$filter_grade = $_GET['filter_grade'] ?? '';
$export = $_GET['export'] ?? '';

// Build WHERE conditions
if (!empty($filter_name)) {
    $where_conditions[] = "no.nursing_name LIKE ?";
    $params[] = "%$filter_name%";
    $param_types .= "s";
}

if (!empty($filter_grade)) {
    $where_conditions[] = "no.grade LIKE ?";
    $params[] = "%$filter_grade%";
    $param_types .= "s";
}

// Build the main query
$base_query = "SELECT no.*,
               (SELECT COUNT(*) FROM patients p WHERE p.assigned_nursing_officer = no.nursing_id) as patient_count
               FROM nursing_officers no";

if (!empty($where_conditions)) {
    $base_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$base_query .= " ORDER BY no.nursing_name ASC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($base_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($base_query);
}



// Handle CSV export
if ($export === 'csv' && $result->num_rows > 0) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="nursing_officer_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Nursing Name', 'Grade', 'Contact Number', 'Assigned Patients'
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
            $row['nursing_name'],
            $row['grade'],
            $row['contact_number'],
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
            <h1>🏥 Nursing Officer Details Report</h1>
            <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Comprehensive nursing staff information with ward assignments</p>
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
                    <label for="filter_name">👤 Nursing Name:</label>
                    <input type="text" id="filter_name" name="filter_name" 
                           value="<?php echo htmlspecialchars($filter_name); ?>" 
                           placeholder="Search by nursing officer name">
                </div>
                
                <div class="form-group">
                    <label for="filter_grade">🏅 Grade:</label>
                    <input type="text" id="filter_grade" name="filter_grade" 
                           value="<?php echo htmlspecialchars($filter_grade ?? ''); ?>" 
                           placeholder="Search by grade">
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: center;">
                <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                <a href="nursing_report.php" class="btn btn-secondary">🔄 Clear Filters</a>
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
                <p style="color: #666; margin: 0;">Found <?php echo $result->num_rows; ?> nursing officer(s) matching your criteria</p>
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
                <table class="table" id="nursingTable">
                    <thead>
                        <tr>
                            <th>👤 Nursing Name</th>
                            <th>🏅 Grade</th>
                            <th>📞 Contact Number</th>
                            <th>👥 Assigned Patients</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($officer = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($officer['nursing_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-blue">
                                        <?php echo htmlspecialchars($officer['grade'] ?: 'Not assigned'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($officer['contact_number'] ?: 'Not provided'); ?></td>
                                <td>
                                    <span class="badge badge-red"><?php echo $officer['patient_count']; ?></span>
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
            
            $total_officers = $stats_result->num_rows;
            $grade_distribution = [];
            $total_patients = 0;
            
            while ($stat = $stats_result->fetch_assoc()) {
                $grade = $stat['grade'] ?: 'Not specified';
                $grade_distribution[$grade] = ($grade_distribution[$grade] ?? 0) + 1;
                
                $total_patients += $stat['patient_count'];
            }
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #00796b;">
                    <div style="font-size: 2rem; font-weight: bold; color: #00796b;"><?php echo $total_officers; ?></div>
                    <div style="color: #666;">Total Officers</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #f57c00;">
                    <div style="font-size: 2rem; font-weight: bold; color: #f57c00;"><?php echo count($grade_distribution); ?></div>
                    <div style="color: #666;">Different Grades</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #d32f2f;">
                    <div style="font-size: 2rem; font-weight: bold; color: #d32f2f;"><?php echo $total_patients; ?></div>
                    <div style="color: #666;">Total Patients</div>
                </div>
            </div>
            
            <!-- Grade Distribution -->
            <div style="margin-top: 2rem;">
                <h4>🏅 Grade Distribution</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <?php foreach ($grade_distribution as $grade => $count): ?>
                        <div style="background: #e0f2f1; padding: 1rem; border-radius: 6px; text-align: center;">
                            <div style="font-weight: bold; color: #00796b;"><?php echo $count; ?> Officers</div>
                            <div style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($grade); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="card">
            <div style="text-align: center; padding: 3rem; color: #666;">
                <h3>📋 No Nursing Officers Found</h3>
                <p>No nursing officers match your current filter criteria. Try adjusting your filters or clearing them to see all officers.</p>
                <a href="nursing_report.php" class="btn btn-primary" style="margin-top: 1rem;">🔄 Show All Officers</a>
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
.badge-green { background: #e8f5e8; color: #388e3c; }
.badge-purple { background: #f3e5f5; color: #7b1fa2; }
.badge-orange { background: #fff3e0; color: #f57c00; }
.badge-red { background: #ffebee; color: #d32f2f; }

@media print {
    .header, .card:first-child { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php include '../../includes/footer.php'; ?>