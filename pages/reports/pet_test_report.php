<?php
include '../../config/db.php';
include '../../includes/header.php';
// AJAX: Patient auto-complete for search_patient field
if (isset($_GET['search_patients']) && !empty($_GET['search_term'])) {
    include_once '../../config/db.php';
    $search_term = $conn->real_escape_string($_GET['search_term']);
    $search_sql = "SELECT p.patient_id, p.calling_name, p.full_name, p.nic, p.hospital_number, p.clinic_number
                   FROM patients p
                   WHERE (p.calling_name LIKE '%$search_term%' 
                       OR p.full_name LIKE '%$search_term%'
                       OR p.nic LIKE '%$search_term%' 
                       OR p.hospital_number LIKE '%$search_term%' 
                       OR p.clinic_number LIKE '%$search_term%')
                      AND (p.patient_status IS NULL OR p.patient_status = '' OR p.patient_status != 'Deceased')
                   ORDER BY p.calling_name LIMIT 10";
    $patients = array();
    $error = null;
    try {
        $search_result = $conn->query($search_sql);
        if ($search_result) {
            while ($row = $search_result->fetch_assoc()) {
                $patients[] = $row;
            }
        } else {
            $error = $conn->error;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    header('Content-Type: application/json');
    if ($error) {
        echo json_encode(["error" => $error, "sql" => $search_sql]);
    } else {
        echo json_encode($patients);
    }
    exit();
}

// Handle filters
$search_patient = isset($_GET['search_patient']) ? trim($_GET['search_patient']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_pet_level = isset($_GET['pet_level']) ? $_GET['pet_level'] : '';
$filter_pd_status = isset($_GET['pd_status']) ? $_GET['pd_status'] : '';

$where = [];
if ($search_patient !== '') {
    $search = $conn->real_escape_string($search_patient);
    $where[] = "(p.calling_name LIKE '%$search%' OR p.full_name LIKE '%$search%' OR p.nic LIKE '%$search%' OR p.hospital_number LIKE '%$search%' OR p.clinic_number LIKE '%$search%')";
}
if ($date_from !== '') {
    $where[] = "pet.test_date >= '" . $conn->real_escape_string($date_from) . "'";
}
if ($date_to !== '') {
    $where[] = "pet.test_date <= '" . $conn->real_escape_string($date_to) . "'";
}
if ($filter_pd_status !== '') {
    $where[] = "pet.pd_status = '" . $conn->real_escape_string($filter_pd_status) . "'";
}
if ($filter_pet_level !== '') {
    $where[] = "pet.pet_level = '" . $conn->real_escape_string($filter_pet_level) . "'";
}

// Build WHERE clause
$where_sql = '';
if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

// Fetch PET records and compute statistics

$all_pet_records = [];
$level_counts = ['High' => 0, 'High Average' => 0, 'Low Average' => 0, 'Low' => 0];
$total_tests = 0;
$total_patients = 0;
try {
    // Query all PET records for the table as before
    $sql = "SELECT pet.*, p.calling_name, p.full_name, p.nic, p.hospital_number, p.clinic_number, u.username as created_by_name
            FROM peritoneal_equilibration_test pet
            LEFT JOIN patients p ON pet.patient_id = p.patient_id
            LEFT JOIN users u ON pet.created_by = u.user_id
            $where_sql
            ORDER BY pet.test_date DESC, pet.created_at DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $all_pet_records[] = $row;
        }
    }

    // Query latest PET level per patient for the stats
    $stats_sql = "SELECT pet.patient_id, pet.pet_level
                  FROM peritoneal_equilibration_test pet
                  INNER JOIN (
                      SELECT patient_id, MAX(test_date) AS max_date
                      FROM peritoneal_equilibration_test
                      GROUP BY patient_id
                  ) latest ON pet.patient_id = latest.patient_id AND pet.test_date = latest.max_date";
    $stats_res = $conn->query($stats_sql);
    if ($stats_res) {
        while ($row = $stats_res->fetch_assoc()) {
            $lvl = trim((string)($row['pet_level'] ?? ''));
            $lvl_lower = mb_strtolower($lvl);
            $map = [
                'high' => 'High',
                'high average' => 'High Average',
                'high average transporter' => 'High Average',
                'low average' => 'Low Average',
                'low' => 'Low',
                'high transporter' => 'High'
            ];
            $canonical = $map[$lvl_lower] ?? null;
            if ($canonical && array_key_exists($canonical, $level_counts)) {
                $level_counts[$canonical]++;
            }
        }
    }
} catch (Exception $e) {
    // keep empty results on error
}

$total_tests = count($all_pet_records);
$patient_ids = array_column($all_pet_records, 'patient_id');
$total_patients = count(array_unique($patient_ids));

// HTML output starts here
?>
<style>
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    background: #f7f8fa;
    font-family: 'Segoe UI', Arial, sans-serif;
    width: 100vw;
    overflow-x: hidden;
}
.container {
    max-width: 1200px;
    margin: 2rem auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    padding: 2rem 2vw;
    width: 98vw;
    min-width: 0;
    box-sizing: border-box;
}
.header.no-print {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1.2rem;
    margin-bottom: 2rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}
@media (max-width: 1100px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 700px) {
    .stats-grid { grid-template-columns: 1fr; }
}
.stat-card {
    border-radius: 10px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.06);
    padding: 1.2rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-height: 90px;
    border: 1px solid #ececec;
    transition: box-shadow 0.2s;
    min-width: 0;
    box-sizing: border-box;
}
.stat-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.10);
}
.stat-value {
    font-size: 2.1rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
    word-break: break-word;
}
.stat-label {
    font-size: 1rem;
    font-weight: 500;
    opacity: 0.85;
}
.card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.06);
    padding: 1.5rem 1rem 2rem 1rem;
    margin-bottom: 2rem;
    border: 1px solid #ececec;
    min-width: 0;
    box-sizing: border-box;
    overflow-x: auto;
}
form.no-print {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1rem 1rem 0.5rem 1rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    min-width: 0;
    box-sizing: border-box;
}
table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    min-width: 900px;
    max-width: 100%;
    table-layout: auto;
    box-sizing: border-box;
    font-size: 1rem;
}
@media (max-width: 1100px) {
    table { min-width: 700px; font-size: 0.95rem; }
}
@media (max-width: 700px) {
    table { min-width: 400px; font-size: 0.92rem; }
    th, td { padding: 0.5rem; }
}
.card {
    /* ...existing code... */
    overflow-x: auto;
}
th, td {
    /* ...existing code... */
    vertical-align: top;
    white-space: nowrap;
}
th, td {
    padding: 1rem;
    text-align: left;
    box-sizing: border-box;
    word-break: break-word;
}
th {
    position: sticky;
    top: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    z-index: 2;
}
tbody tr {
    transition: background 0.15s;
}
tbody tr:hover {
    background: #f1f5fb;
}
.search-results {
    font-size: 1rem;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    max-width: 100vw;
    box-sizing: border-box;
}
.search-item {
    padding: 0.5rem;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}
.search-item:last-child { border-bottom: none; }
.search-item.active { background: #e8f0ff; }
.btn {
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-primary {
    background: #667eea;
    color: #fff;
}
.btn-primary:hover {
    background: #4b5bdc;
}
.btn-secondary {
    background: #e0e7ef;
    color: #333;
}
.btn-secondary:hover {
    background: #cfd8e3;
}
@media (max-width: 1100px) {
    .container { max-width: 99vw; padding: 1rem 0.5rem; }
    table { min-width: 700px; }
}
@media (max-width: 700px) {
    .container { padding: 0.5rem 0.1rem; }
    .stats-grid { grid-template-columns: 1fr; }
    .header.no-print { flex-direction: column; gap: 1rem; }
    table { min-width: 400px; }
}
</style>
    <div class="container">
        <div class="header no-print">
            <div>
                <h1>🧪 PET Test Report</h1>
                <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Peritoneal Equilibration Test Results and History</p>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-primary" style="margin-right: 1rem;">🖨️ Print Report</button>
                <a href="report_list.php" class="btn btn-secondary">← Back to Reports</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div class="stat-value"><?php echo (int)$total_tests; ?></div>
                <div class="stat-label">Total PET Tests</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div class="stat-value"><?php echo (int)$total_patients; ?></div>
                <div class="stat-label">Unique Patients</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                <div class="stat-value"><?php echo (int)($level_counts['High'] ?? 0); ?></div>
                <div class="stat-label">High Transporters</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); color: white;">
                <div class="stat-value"><?php echo (int)($level_counts['High Average'] ?? 0); ?></div>
                <div class="stat-label">High Average</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;">
                <div class="stat-value"><?php echo (int)($level_counts['Low Average'] ?? 0); ?></div>
                <div class="stat-label">Low Average</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%); color: #333;">
                <div class="stat-value"><?php echo (int)($level_counts['Low'] ?? 0); ?></div>
                <div class="stat-label">Low Transporters</div>
            </div>
        </div>

        <!-- PET Records Table with Filters -->
        <div class="card">
            <h3>📊 All PET Test Records</h3>
            <!-- Filters -->
            <form method="get" class="no-print" style="margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                <div>
                    <label for="search_patient" style="font-weight: 600; color: #2c3e50;">Search Patient</label><br>
                    <input type="text" id="search_patient" name="search_patient" value="<?php echo htmlspecialchars($search_patient); ?>" placeholder="Name, NIC, PHN, Clinic #" style="padding: 0.5rem; border-radius: 6px; border: 1px solid #ccc; min-width: 180px;">
                </div>
                <div>
                    <label for="date_from" style="font-weight: 600; color: #2c3e50;">From</label><br>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" style="padding: 0.5rem; border-radius: 6px; border: 1px solid #ccc;">
                </div>
                <div>
                    <label for="date_to" style="font-weight: 600; color: #2c3e50;">To</label><br>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" style="padding: 0.5rem; border-radius: 6px; border: 1px solid #ccc;">
                </div>
                <div>
                    <label for="pet_level" style="font-weight: 600; color: #2c3e50;">PET Level</label><br>
                    <select id="pet_level" name="pet_level" style="padding: 0.5rem; border-radius: 6px; border: 1px solid #ccc; min-width: 120px;">
                        <option value="">All</option>
                        <option value="High" <?php if($filter_pet_level==='High') echo 'selected'; ?>>High</option>
                        <option value="High Average" <?php if($filter_pet_level==='High Average') echo 'selected'; ?>>High Average</option>
                        <option value="Low Average" <?php if($filter_pet_level==='Low Average') echo 'selected'; ?>>Low Average</option>
                        <option value="Low" <?php if($filter_pet_level==='Low') echo 'selected'; ?>>Low</option>
                    </select>
                </div>
                <div>
                    <label for="pd_status" style="font-weight: 600; color: #2c3e50;">PD Status</label><br>
                    <select id="pd_status" name="pd_status" style="padding: 0.5rem; border-radius: 6px; border: 1px solid #ccc; min-width: 100px;">
                        <option value="">All</option>
                        <option value="CAPD" <?php if($filter_pd_status==='CAPD') echo 'selected'; ?>>CAPD</option>
                        <option value="APD" <?php if($filter_pd_status==='APD') echo 'selected'; ?>>APD</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.2rem; border-radius: 6px;">🔍 Filter</button>
                    <a href="pet_test_report.php" class="btn btn-secondary" style="padding: 0.6rem 1.2rem; border-radius: 6px; margin-left: 0.5rem;">Clear</a>
                </div>
            </form>
            <?php if (!empty($all_pet_records)): ?>
                <div style="overflow-x: auto; margin-top: 1.5rem;">
                    <table style="width: 100%; border-collapse: collapse; background: white;">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <th style="padding: 1rem; text-align: left;">Patient</th>
                                <th style="padding: 1rem; text-align: left;">NIC</th>
                                <th style="padding: 1rem; text-align: left;">PHN</th>
                                <th style="padding: 1rem; text-align: left;">Clinic</th>
                                <th style="padding: 1rem; text-align: left;">Test Date</th>
                                <th style="padding: 1rem; text-align: center;">PET Level</th>
                                <th style="padding: 1rem; text-align: center;">D/P Creatinine</th>
                                <th style="padding: 1rem; text-align: center;">D/D0 Glucose</th>
                                <th style="padding: 1rem; text-align: center;">Ultrafiltration</th>
                                <th style="padding: 1rem; text-align: left;">Notes</th>
                                <th style="padding: 1rem; text-align: left;">Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_pet_records as $record): ?>
                                <tr style="border-bottom: 1px solid #e9ecef;">
                                    <td style="padding: 1rem;">
                                        <div style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($record['calling_name']); ?></div>
                                        <div style="font-size: 0.85rem; color: #7f8c8d;"><?php echo htmlspecialchars($record['full_name']); ?></div>
                                    </td>
                                    <td style="padding: 1rem; font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($record['nic']); ?></td>
                                    <td style="padding: 1rem;">
                                        <?php echo $record['hospital_number'] ? '<span style="background: #e8f5e8; color: #2d6a2d; padding: 0.3rem 0.6rem; border-radius: 4px; font-weight: 500; font-size: 0.85rem;">' . htmlspecialchars($record['hospital_number']) . '</span>' : '<span style="color: #999;">-</span>'; ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php echo $record['clinic_number'] ? '<span style="background: #e3f2fd; color: #1565c0; padding: 0.3rem 0.6rem; border-radius: 4px; font-weight: 500; font-size: 0.85rem;">' . htmlspecialchars($record['clinic_number']) . '</span>' : '<span style="color: #999;">-</span>'; ?>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 600;"><?php echo date('M j, Y', strtotime($record['test_date'])); ?></td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <?php 
                                        $level_colors = [
                                            'High' => 'background: #ffebee; color: #c62828;',
                                            'High Average' => 'background: #fff3e0; color: #e65100;',
                                            'Low Average' => 'background: #e3f2fd; color: #1565c0;',
                                            'Low' => 'background: #e8f5e9; color: #2e7d32;'
                                        ];
                                        $style = $level_colors[$record['pet_level']] ?? 'background: #f5f5f5; color: #666;';
                                        ?>
                                        <span style="<?php echo $style; ?> padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem; white-space: nowrap; display: inline-block; min-width: 90px; text-align: center;">
                                            <?php echo htmlspecialchars($record['pet_level']); ?>
                                        </span>
                                        <?php if (!empty($record['pd_status'])): ?>
                                            <div style="margin-top: 0.25rem; font-weight: bold; font-size: 0.95em; color: <?php echo $record['pd_status']==='CAPD' ? '#1565c0' : ($record['pd_status']==='APD' ? '#8d5524' : '#888'); ?>; text-align: center;">
                                                <?php echo htmlspecialchars($record['pd_status']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center; font-family: monospace; font-weight: 600;">
                                        <?php echo $record['d_p_creatinine'] ? number_format($record['d_p_creatinine'], 2) : '<span style="color: #999;">-</span>'; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center; font-family: monospace; font-weight: 600;">
                                        <?php echo $record['d_d0_glucose'] ? number_format($record['d_d0_glucose'], 2) : '<span style="color: #999;">-</span>'; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center; font-weight: 600;">
                                        <?php echo $record['ultrafiltration'] ? $record['ultrafiltration'] . ' mL' : '<span style="color: #999;">-</span>'; ?>
                                    </td>
                                    <td style="padding: 1rem; max-width: 200px; font-size: 0.9rem;">
                                        <?php echo $record['notes'] ? htmlspecialchars($record['notes']) : '<span style="color: #999; font-style: italic;">-</span>'; ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span style="background: #e8f5e9; color: #2e7d32; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($record['created_by_name']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: #6c757d;">
                    <h3 style="font-size: 3rem; margin-bottom: 1rem;">🧪</h3>
                    <h3>No PET Test Records Found</h3>
                    <p>No Peritoneal Equilibration Test records have been added to the system yet.</p>
                    <a href="../patients/patient_list.php" class="btn btn-primary" style="margin-top: 1rem;">Go to Patient List</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Legend -->
        <?php if (!empty($all_pet_records)): ?>
        <div class="card" style="margin-top: 2rem;">
            <h3>📋 PET Level Classification</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <div style="padding: 1rem; background: #ffebee; border-left: 4px solid #c62828; border-radius: 4px;">
                    <strong style="color: #c62828;">High Transporter</strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">Fast solute transport, poor ultrafiltration</p>
                </div>
                <div style="padding: 1rem; background: #fff3e0; border-left: 4px solid #e65100; border-radius: 4px;">
                    <strong style="color: #e65100;">High Average Transporter</strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">Moderate-fast transport characteristics</p>
                </div>
                <div style="padding: 1rem; background: #e3f2fd; border-left: 4px solid #1565c0; border-radius: 4px;">
                    <strong style="color: #1565c0;">Low Average Transporter</strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">Moderate-slow transport characteristics</p>
                </div>
                <div style="padding: 1rem; background: #e8f5e9; border-left: 4px solid #2e7d32; border-radius: 4px;">
                    <strong style="color: #2e7d32;">Low Transporter</strong>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">Slow solute transport, good ultrafiltration</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<script>
// Patient auto-complete for Search Patient filter
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_patient');
    if (!searchInput) return;
    let dropdown = document.createElement('div');
    dropdown.id = 'search_patient_results';
    dropdown.className = 'search-results';
    dropdown.style.position = 'absolute';
    dropdown.style.top = '100%';
    dropdown.style.left = '0';
    dropdown.style.right = '0';
    dropdown.style.background = 'white';
    dropdown.style.border = '1px solid #ddd';
    dropdown.style.borderTop = 'none';
    dropdown.style.maxHeight = '200px';
    dropdown.style.overflowY = 'auto';
    dropdown.style.zIndex = '1000';
    dropdown.style.display = 'none';
    searchInput.parentNode.style.position = 'relative';
    searchInput.parentNode.appendChild(dropdown);

    let searchTimeout;
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        clearTimeout(searchTimeout);
        if (searchTerm.length < 2) {
            dropdown.style.display = 'none';
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('patient_detail_search.php?ajax_search=1&term=' + encodeURIComponent(searchTerm))
                .then(response => response.json())
                .then(patients => {
                    if (!Array.isArray(patients) || patients.length === 0) {
                        dropdown.innerHTML = '<div class="search-item">No patients found</div>';
                    } else {
                        dropdown.innerHTML = patients.map(patient =>
                            `<div class="search-item" style="padding:0.5rem;cursor:pointer;border-bottom:1px solid #eee;" tabindex="0" data-id="${patient.patient_id}">
                                <strong>${escapeHtml(patient.calling_name)}</strong> (${escapeHtml(patient.full_name)})<br>
                                <small>NIC: ${escapeHtml(patient.nic)} | PHN: ${escapeHtml(patient.hospital_number || '-')} | Clinic: ${escapeHtml(patient.clinic_number || '-')}</small>
                            </div>`
                        ).join('');
                        // Add click and hover listeners
                        Array.from(dropdown.children).forEach((item, idx) => {
                            item.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                searchInput.value = patients[idx].calling_name;
                                dropdown.style.display = 'none';
                                searchInput.form && searchInput.form.submit();
                            });
                            item.addEventListener('mouseover', function() {
                                selectedIndex = idx;
                                updateActive();
                            });
                        });
                        // reset selection when new results appear
                        selectedIndex = -1;
                    }
                    dropdown.style.display = 'block';
                })
                .catch(error => {
                    dropdown.innerHTML = '<div class="search-item" style="color:#b71c1c;">Error searching</div>';
                    dropdown.style.display = 'block';
                });
        }, 200);
    });

    // Escape HTML utility
    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function(m) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'' :'&#39;'}[m]);
        });
    }

    // Keyboard navigation for dropdown
    let selectedIndex = -1;
    function updateActive() {
        Array.from(dropdown.children).forEach((c, i) => {
            c.classList.toggle('active', i === selectedIndex);
            if (i === selectedIndex) {
                c.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    searchInput.addEventListener('keydown', function(e) {
        const count = dropdown.children.length;
        if (dropdown.style.display === 'none' || count === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = (selectedIndex + 1) % count;
            updateActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = (selectedIndex - 1 + count) % count;
            updateActive();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && dropdown.children[selectedIndex]) {
                dropdown.children[selectedIndex].dispatchEvent(new MouseEvent('mousedown'));
            }
        } else if (e.key === 'Escape') {
            dropdown.style.display = 'none';
            selectedIndex = -1;
        }
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
            selectedIndex = -1;
        }
    });
});
</script>
