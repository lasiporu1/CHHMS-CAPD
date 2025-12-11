<?php
include '../../config/db.php';

// Search patients for AJAX - MUST be before any HTML output
if (isset($_GET['search_patients']) && !empty($_GET['search_term'])) {
    $search_term = $conn->real_escape_string($_GET['search_term']);
    $search_sql = "SELECT p.patient_id, p.calling_name, p.full_name, p.nic, p.hospital_number, p.clinic_number,
                          wa.admission_id, wa.admission_status
                   FROM patients p
                   LEFT JOIN ward_admissions wa ON p.patient_id = wa.patient_id AND wa.admission_status = 'Active'
                   WHERE p.calling_name LIKE '%$search_term%' 
                      OR p.nic LIKE '%$search_term%' 
                      OR p.hospital_number LIKE '%$search_term%' 
                      OR p.clinic_number LIKE '%$search_term%'
                   ORDER BY p.calling_name LIMIT 10";
    $search_result = $conn->query($search_sql);
    
    $patients = array();
    while ($row = $search_result->fetch_assoc()) {
        $patients[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($patients);
    exit();
}

include '../../includes/header.php';

// Handle form submission and build query
$where_conditions = [];
$params = [];
$param_types = "";

// Get filter values
$filter_patient_name = $_GET['filter_patient_name'] ?? '';
$filter_admission_id = $_GET['filter_admission_id'] ?? '';
$filter_ward = $_GET['filter_ward'] ?? '';
$filter_doctor = $_GET['filter_doctor'] ?? '';
$filter_nursing_officer = $_GET['filter_nursing_officer'] ?? '';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';
$filter_discharge_status = $_GET['filter_discharge_status'] ?? '';
$filter_admission_reason = $_GET['filter_admission_reason'] ?? '';
$export = $_GET['export'] ?? '';

// Build WHERE conditions
if (!empty($filter_patient_name)) {
    $escaped_name = $conn->real_escape_string($filter_patient_name);
    $where_conditions[] = "(p.calling_name LIKE '%$escaped_name%' OR p.full_name LIKE '%$escaped_name%' OR p.nic LIKE '%$escaped_name%' OR p.hospital_number LIKE '%$escaped_name%' OR p.clinic_number LIKE '%$escaped_name%' OR p.contact_number LIKE '%$escaped_name%')";
}

if (!empty($filter_admission_id)) {
    $escaped_id = $conn->real_escape_string($filter_admission_id);
    $where_conditions[] = "wa.admission_id LIKE '%$escaped_id%'";
}

if (!empty($filter_ward)) {
    $escaped_ward = $conn->real_escape_string($filter_ward);
    $where_conditions[] = "wa.ward_bed LIKE '%$escaped_ward%'";
}

if (!empty($filter_doctor)) {
    $escaped_doctor = $conn->real_escape_string($filter_doctor);
    $where_conditions[] = "d.doctor_name LIKE '%$escaped_doctor%'";
}

if (!empty($filter_nursing_officer)) {
    $escaped_nursing = $conn->real_escape_string($filter_nursing_officer);
    $where_conditions[] = "no.nursing_name LIKE '%$escaped_nursing%'";
}

if (!empty($filter_date_from)) {
    $escaped_date_from = $conn->real_escape_string($filter_date_from);
    $where_conditions[] = "wa.admission_date >= '$escaped_date_from'";
}

if (!empty($filter_date_to)) {
    $escaped_date_to = $conn->real_escape_string($filter_date_to);
    $where_conditions[] = "wa.admission_date <= '$escaped_date_to'";
}

if (!empty($filter_discharge_status)) {
    if ($filter_discharge_status === 'discharged') {
        $where_conditions[] = "wa.admission_status = 'Discharged'";
    } elseif ($filter_discharge_status === 'active') {
        $where_conditions[] = "wa.admission_status = 'Active'";
    }
}

if (!empty($filter_admission_reason)) {
    $escaped_reason = $conn->real_escape_string($filter_admission_reason);
    $where_conditions[] = "ar.reason_name LIKE '%$escaped_reason%'";
}

// Build the main query
$base_query = "SELECT wa.*, 
               p.calling_name, p.full_name, p.nic, p.contact_number, p.hospital_number, p.clinic_number,
               d.doctor_name, d.specialization,
               no.nursing_name,
               ar.reason_name as admission_reason,
               wa.ward_bed,
               DATEDIFF(COALESCE(wa.discharge_date, CURDATE()), wa.admission_date) as length_of_stay,
               wa.admission_status as status
               FROM ward_admissions wa
               LEFT JOIN patients p ON wa.patient_id = p.patient_id
               LEFT JOIN doctors d ON wa.attending_doctor_id = d.doctor_id
               LEFT JOIN nursing_officers no ON wa.nursing_officer_id = no.nursing_id
               LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id";

if (!empty($where_conditions)) {
    $base_query .= " WHERE " . implode(" AND ", $where_conditions);
}

$base_query .= " ORDER BY wa.admission_date DESC, wa.admission_id DESC";

// Execute query
$result = $conn->query($base_query);

// Get dropdown options
$wards = $conn->query("SELECT DISTINCT ward_bed FROM ward_admissions WHERE ward_bed IS NOT NULL AND ward_bed != '' ORDER BY ward_bed ASC");
$doctors = $conn->query("SELECT doctor_id, doctor_name FROM doctors ORDER BY doctor_name ASC");
$nursing_officers = $conn->query("SELECT nursing_id, nursing_name FROM nursing_officers ORDER BY nursing_name ASC");
$admission_reasons = $conn->query("SELECT reason_id, reason_name FROM admission_reasons ORDER BY reason_name ASC");

// Handle CSV export
if ($export === 'csv' && $result->num_rows > 0) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ward_admission_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Admission ID', 'Patient Name', 'NIC', 'Hospital Number', 'Clinic Number', 'Contact', 'Ward/Bed',
        'Admission Date', 'Admission Time', 'Admission Reason', 'Attending Doctor', 'Specialization',
        'Nursing Officer', 'Discharge Date', 'Length of Stay (Days)', 'Status'
    ]);
    
    // Reset result pointer
    $result = $conn->query($base_query);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['admission_id'],
            $row['calling_name'],
            $row['nic'],
            $row['hospital_number'],
            $row['clinic_number'],
            $row['contact_number'],
            $row['ward_bed'],
            $row['admission_date'],
            $row['admission_time'],
            $row['admission_reason'],
            $row['doctor_name'],
            $row['specialization'],
            $row['nursing_name'],
            $row['discharge_date'],
            $row['length_of_stay'],
            $row['status']
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
    <div class="header">
        <div>
            <h1>🏨 Ward Admissions Report</h1>
            <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Comprehensive admission records with advanced filtering</p>
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
                
                <div class="form-group" style="position: relative;">
                    <label for="filter_patient_name">👤 Patient Search:</label>
                    <input type="text" id="filter_patient_name" name="filter_patient_name" 
                           value="<?php echo htmlspecialchars($filter_patient_name); ?>" 
                           placeholder="🔍 Search by name, NIC, Hospital #, Clinic #, Contact #" autocomplete="off">
                    <div class="search-results" id="search_results" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 1000; display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label for="filter_admission_id">🆔 Admission ID:</label>
                    <input type="text" id="filter_admission_id" name="filter_admission_id" 
                           value="<?php echo htmlspecialchars($filter_admission_id); ?>" 
                           placeholder="Enter admission ID">
                </div>
                
                <div class="form-group">
                    <label for="filter_ward">🏥 Ward:</label>
                    <select id="filter_ward" name="filter_ward">
                        <option value="">All Wards/Beds</option>
                        <?php while ($ward = $wards->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($ward['ward_bed']); ?>" 
                                    <?php echo $filter_ward === $ward['ward_bed'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ward['ward_bed']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_doctor">👨‍⚕️ Attending Doctor:</label>
                    <select id="filter_doctor" name="filter_doctor">
                        <option value="">All Doctors</option>
                        <?php while ($doctor = $doctors->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($doctor['doctor_name']); ?>" 
                                    <?php echo $filter_doctor === $doctor['doctor_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_nursing_officer">🏥 Nursing Officer:</label>
                    <select id="filter_nursing_officer" name="filter_nursing_officer">
                        <option value="">All Nursing Officers</option>
                        <?php while ($no = $nursing_officers->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($no['nursing_name']); ?>" 
                                    <?php echo $filter_nursing_officer === $no['nursing_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($no['nursing_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_admission_reason">📋 Admission Reason:</label>
                    <select id="filter_admission_reason" name="filter_admission_reason">
                        <option value="">All Reasons</option>
                        <?php while ($reason = $admission_reasons->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($reason['reason_name']); ?>" 
                                    <?php echo $filter_admission_reason === $reason['reason_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($reason['reason_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_date_from">📅 Admission From:</label>
                    <input type="date" id="filter_date_from" name="filter_date_from" 
                           value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label for="filter_date_to">📅 Admission To:</label>
                    <input type="date" id="filter_date_to" name="filter_date_to" 
                           value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                
                <div class="form-group">
                    <label for="filter_discharge_status">📊 Discharge Status:</label>
                    <select id="filter_discharge_status" name="filter_discharge_status">
                        <option value="">All Patients</option>
                        <option value="active" <?php echo $filter_discharge_status === 'active' ? 'selected' : ''; ?>>Currently Admitted</option>
                        <option value="discharged" <?php echo $filter_discharge_status === 'discharged' ? 'selected' : ''; ?>>Discharged</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: center;">
                <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                <a href="admission_report.php" class="btn btn-secondary">🔄 Clear Filters</a>
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
                <p style="color: #666; margin: 0;">Found <?php echo $result->num_rows; ?> admission record(s) matching your criteria</p>
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
                <table class="table" id="admissionsTable">
                    <thead>
                        <tr>
                            <th>🆔 Admission ID</th>
                            <th>👤 Patient</th>
                            <th>🏥 Ward & Bed</th>
                            <th>📅 Admission</th>
                            <th>📋 Reason</th>
                            <th>👨‍⚕️ Doctor</th>
                            <th>🏥 Nursing Officer</th>
                            <th>📊 Status</th>
                            <th>📏 Stay Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($admission = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($admission['admission_id']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($admission['calling_name']); ?></strong>
                                        <?php if ($admission['clinic_number']): ?>
                                        <br>
                                        <small style="color: #666;">🏥 C#: <?php echo htmlspecialchars($admission['clinic_number']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="badge badge-blue"><?php echo htmlspecialchars($admission['ward_bed'] ?: 'Not assigned'); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo date('M j, Y', strtotime($admission['admission_date'])); ?></strong>
                                        <br>
                                        <small style="color: #666;"><?php echo date('g:i A', strtotime($admission['admission_time'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-purple">
                                        <?php echo htmlspecialchars($admission['admission_reason'] ?: 'Not specified'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($admission['doctor_name']): ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($admission['doctor_name']); ?></strong>
                                            <br>
                                            <small style="color: #666;"><?php echo htmlspecialchars($admission['specialization'] ?: 'General'); ?></small>
                                        </div>
                                    <?php else: ?>
                                        Not assigned
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($admission['nursing_name'] ?: 'Not assigned'); ?></td>
                                <td>
                                    <?php if ($admission['status'] === 'Active'): ?>
                                        <span class="badge badge-green">🟢 Active</span>
                                    <?php elseif ($admission['status'] === 'Discharged'): ?>
                                        <span class="badge badge-gray">⚪ Discharged</span>
                                        <br>
                                        <small style="color: #666;"><?php echo $admission['discharge_date'] ? date('M j, Y', strtotime($admission['discharge_date'])) : 'No date'; ?></small>
                                    <?php else: ?>
                                        <span class="badge badge-orange">🟡 <?php echo htmlspecialchars($admission['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-orange">
                                        <?php echo $admission['length_of_stay']; ?> day(s)
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Statistics Summary -->
        <div class="card">
            <h3>📈 Admission Statistics</h3>
            <?php
            // Reset result for statistics
            $stats_result = $conn->query($base_query);
            
            $total_admissions = $stats_result->num_rows;
            $active_admissions = 0;
            $discharged_admissions = 0;
            $ward_distribution = [];
            $total_stay_days = 0;
            $doctor_distribution = [];
            
            while ($stat = $stats_result->fetch_assoc()) {
                if ($stat['status'] === 'Active') {
                    $active_admissions++;
                } else {
                    $discharged_admissions++;
                }
                
                $ward = $stat['ward_bed'] ?: 'Not specified';
                $ward_distribution[$ward] = ($ward_distribution[$ward] ?? 0) + 1;
                
                $doctor = $stat['doctor_name'] ?: 'Not assigned';
                $doctor_distribution[$doctor] = ($doctor_distribution[$doctor] ?? 0) + 1;
                
                $total_stay_days += $stat['length_of_stay'];
            }
            
            $avg_stay = $total_admissions > 0 ? round($total_stay_days / $total_admissions, 1) : 0;
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #827717;">
                    <div style="font-size: 2rem; font-weight: bold; color: #827717;"><?php echo $total_admissions; ?></div>
                    <div style="color: #666;">Total Admissions</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #28a745;">
                    <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?php echo $active_admissions; ?></div>
                    <div style="color: #666;">Currently Admitted</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #6c757d;">
                    <div style="font-size: 2rem; font-weight: bold; color: #6c757d;"><?php echo $discharged_admissions; ?></div>
                    <div style="color: #666;">Discharged</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; text-align: center; border-left: 4px solid #ffc107;">
                    <div style="font-size: 2rem; font-weight: bold; color: #e68900;"><?php echo $avg_stay; ?></div>
                    <div style="color: #666;">Avg Stay (Days)</div>
                </div>
            </div>
            
            <!-- Ward Distribution -->
            <div style="margin-top: 2rem;">
                <h4>🏥 Ward Distribution</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <?php foreach ($ward_distribution as $ward => $count): ?>
                        <div style="background: #e8f5e8; padding: 1rem; border-radius: 6px; text-align: center;">
                            <div style="font-weight: bold; color: #28a745;"><?php echo $count; ?> Admissions</div>
                            <div style="color: #666; font-size: 0.9rem;"><?php echo htmlspecialchars($ward); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="card">
            <div style="text-align: center; padding: 3rem; color: #666;">
                <h3>📋 No Admission Records Found</h3>
                <p>No admission records match your current filter criteria. Try adjusting your filters or clearing them to see all admissions.</p>
                <a href="admission_report.php" class="btn btn-primary" style="margin-top: 1rem;">🔄 Show All Admissions</a>
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
.badge-gray { background: #f5f5f5; color: #616161; }
.badge-orange { background: #fff3e0; color: #f57c00; }

.search-results {
    border-radius: 0 0 8px 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.search-item {
    padding: 12px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s;
}

.search-item:hover {
    background-color: #f8f9fa;
}

.search-item:last-child {
    border-bottom: none;
}

@media print {
    .header, .card:first-child { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
    .search-results { display: none; }
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('filter_patient_name');
        const searchResults = document.getElementById('search_results');
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(function() {
                fetch(`admission_report.php?search_patients=1&search_term=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(patients => {
                        displaySearchResults(patients);
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                    });
            }, 300);
        });
        
        function displaySearchResults(patients) {
            if (patients.length === 0) {
                searchResults.innerHTML = '<div class="search-item">No patients found</div>';
            } else {
                searchResults.innerHTML = patients.map(patient => 
                    `<div class="search-item" onclick="selectPatient('${patient.calling_name}')">
                        <strong>${patient.calling_name}</strong> (${patient.full_name})<br>
                        <small style="color: #666;">NIC: ${patient.nic} | H#: ${patient.hospital_number || 'Not assigned'} | C#: ${patient.clinic_number || 'Not assigned'}</small>
                        ${patient.admission_status === 'Active' ? '<br><small style="color: #28a745; font-weight: 500;">🟢 Currently Admitted</small>' : ''}
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
    });
    
    function selectPatient(callingName) {
        document.getElementById('filter_patient_name').value = callingName;
        document.getElementById('search_results').style.display = 'none';
        
        // Auto-submit the search form
        document.getElementById('filterForm').submit();
    }
</script>

<?php include '../../includes/footer.php'; ?>