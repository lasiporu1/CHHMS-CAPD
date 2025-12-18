<?php
include '../../config/db.php';
session_start();

// AJAX search handler for admissions (for search box)
if (isset($_GET['search_admissions']) && $_GET['search_admissions'] == '1') {
    header('Content-Type: application/json');
    $search_term = isset($_GET['search_term']) ? $conn->real_escape_string($_GET['search_term']) : '';
    $results = [];
    if ($search_term && strlen($search_term) >= 2) {
        // Show all patients, regardless of death status
        $sql = "SELECT wa.admission_id, p.patient_id, p.calling_name, p.full_name, p.nic, p.hospital_number, p.clinic_number, ar.reason_name, wa.ward_bed
                FROM ward_admissions wa
                INNER JOIN (
                    SELECT patient_id, MAX(admission_date) AS max_date, MAX(admission_time) AS max_time
                    FROM ward_admissions
                    GROUP BY patient_id
                ) latest ON wa.patient_id = latest.patient_id AND wa.admission_date = latest.max_date AND wa.admission_time = latest.max_time
                LEFT JOIN patients p ON wa.patient_id = p.patient_id
                LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
                WHERE (p.calling_name LIKE '%$search_term%' OR p.full_name LIKE '%$search_term%' OR p.nic LIKE '%$search_term%' 
                       OR p.hospital_number LIKE '%$search_term%' OR p.clinic_number LIKE '%$search_term%')
                ORDER BY wa.admission_date DESC, wa.admission_time DESC
                LIMIT 20";
        $query = $conn->query($sql);
        if ($query) {
            $seen_patients = [];
            while ($row = $query->fetch_assoc()) {
                if (!in_array($row['patient_id'], $seen_patients)) {
                    $results[] = $row;
                    $seen_patients[] = $row['patient_id'];
                }
            }
        }
    }
    echo json_encode($results);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Create admission_reasons table if it doesn't exist
$create_reasons_table_sql = "CREATE TABLE IF NOT EXISTS admission_reasons (
    reason_id INT AUTO_INCREMENT PRIMARY KEY,
    reason_name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (!$conn->query($create_reasons_table_sql)) {
    die("Error creating admission_reasons table: " . $conn->error);
}

// Insert default admission reasons if table is empty
$check_sql = "SELECT COUNT(*) as count FROM admission_reasons";
$check_result = $conn->query($check_sql);
$count = $check_result->fetch_assoc()['count'];

if ($count == 0) {
    $default_reasons = [
        ['Medical Treatment', 'General medical care and treatment'],
        ['Surgical Procedure', 'Scheduled surgical intervention'],
        ['Post-operative Care', 'Recovery and monitoring after surgery'],
        ['Infection Treatment', 'Treatment of various infections'],
        ['Chronic Disease Management', 'Management of ongoing chronic conditions'],
        ['Emergency Care', 'Urgent medical intervention required'],
        ['Diagnostic Procedures', 'Medical tests and diagnostic evaluations'],
        ['Medication Management', 'Adjustment or monitoring of medications'],
        ['Hypertension Control', 'Blood pressure management and monitoring'],
        ['Nutritional Assessment', 'Evaluation and management of nutritional status'],
        ['Routine Follow-up', 'Regular monitoring and check-up'],
        ['Complication Management', 'Treatment of medical complications'],
        ['Pre-operative Evaluation', 'Assessment before surgical procedures'],
        ['Rehabilitation', 'Physical or medical rehabilitation services'],
        ['Pain Management', 'Treatment and control of chronic or acute pain']
    ];
    
    foreach ($default_reasons as $reason) {
        $insert_sql = "INSERT INTO admission_reasons (reason_name, description) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ss", $reason[0], $reason[1]);
        $stmt->execute();
    }
}

// Create ward_admissions table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS ward_admissions (
    admission_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    admission_reason_id INT NOT NULL,
    admission_date DATE NOT NULL,
    admission_time TIME NOT NULL,
    attending_doctor_id INT,
    nursing_officer_id INT,
    ward_bed VARCHAR(50),
    admission_notes TEXT,
    admission_status ENUM('Active', 'Discharged', 'Transferred') DEFAULT 'Active',
    discharge_date DATE NULL,
    discharge_time TIME NULL,
    discharge_notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (!$conn->query($create_table_sql)) {
    die("Error creating ward_admissions table: " . $conn->error);
}

// Create investigations table if it doesn't exist
$create_investigations_sql = "CREATE TABLE IF NOT EXISTS investigations (
    investigation_id INT AUTO_INCREMENT PRIMARY KEY,
    admission_id INT NOT NULL,
    investigation_type VARCHAR(100) NOT NULL,
    investigation_name VARCHAR(255) NOT NULL,
    ordered_date DATE NOT NULL,
    ordered_time TIME NOT NULL,
    ordered_by INT NOT NULL,
    result_value TEXT,
    result_unit VARCHAR(50),
    normal_range VARCHAR(100),
    result_status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
    result_date DATE NULL,
    result_time TIME NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (!$conn->query($create_investigations_sql)) {
    die("Error creating investigations table: " . $conn->error);
}

// Create reports table if it doesn't exist
$create_reports_sql = "CREATE TABLE IF NOT EXISTS reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    admission_id INT NOT NULL,
    report_type VARCHAR(100) NOT NULL,
    report_title VARCHAR(255) NOT NULL,
    report_content TEXT NOT NULL,
    report_date DATE NOT NULL,
    report_time TIME NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (!$conn->query($create_reports_sql)) {
    die("Error creating reports table: " . $conn->error);
}

// Create medicines table if it doesn't exist
$create_medicines_sql = "CREATE TABLE IF NOT EXISTS medicines (
    medicine_id INT AUTO_INCREMENT PRIMARY KEY,
    admission_id INT NOT NULL,
    medicine_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(100) NOT NULL,
    frequency VARCHAR(100) NOT NULL,
    route VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    prescribed_by INT NOT NULL,
    instructions TEXT,
    status ENUM('Active', 'Completed', 'Discontinued') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (!$conn->query($create_medicines_sql)) {
    die("Error creating medicines table: " . $conn->error);
}

// Create ward_beds table if it doesn't exist
$create_beds_table = "CREATE TABLE IF NOT EXISTS ward_beds (
    bed_id INT AUTO_INCREMENT PRIMARY KEY,
    ward_name VARCHAR(100) NOT NULL,
    bed_number VARCHAR(50) NOT NULL,
    bed_type ENUM('General', 'ICU', 'CCU', 'Private', 'Semi-Private', 'Emergency') DEFAULT 'General',
    bed_status ENUM('Available', 'Occupied', 'Maintenance', 'Reserved') DEFAULT 'Available',
    room_number VARCHAR(50),
    floor_number VARCHAR(10),
    equipment TEXT,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ward_bed (ward_name, bed_number)
)";
if (!$conn->query($create_beds_table)) {
    die("Error creating ward_beds table: " . $conn->error);
}

// Get current user role
$current_user_sql = "SELECT user_role FROM users WHERE user_id = {$_SESSION['user_id']}";
$current_user_result = $conn->query($current_user_sql);
$current_user = $current_user_result->fetch_assoc();
$is_admin = ($current_user['user_role'] == 'Admin');

// Delete admission if requested (only for Admin)
if (isset($_GET['delete']) && $is_admin) {
    $id = $conn->real_escape_string($_GET['delete']);
    $sql = "DELETE FROM ward_admissions WHERE admission_id = $id";
    if ($conn->query($sql) === TRUE) {
        header("Location: admission_list.php?deleted=success");
        exit();
    } else {
        header("Location: admission_list.php?deleted=error");
        exit();
    }
}

// Search functionality
$search = '';
$sql = "SELECT wa.*, 
               p.calling_name, p.full_name, p.nic, p.hospital_number, p.clinic_number,
               ar.reason_name,
               d.doctor_name, d.specialization,
               no.nursing_name,
               u.username as created_by_name
        FROM ward_admissions wa
        LEFT JOIN patients p ON wa.patient_id = p.patient_id
        LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
        LEFT JOIN doctors d ON wa.attending_doctor_id = d.doctor_id
        LEFT JOIN nursing_officers no ON wa.nursing_officer_id = no.nursing_id
        LEFT JOIN users u ON wa.created_by = u.user_id";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " WHERE (p.calling_name LIKE '%$search%' OR p.full_name LIKE '%$search%' OR p.nic LIKE '%$search%' 
              OR p.hospital_number LIKE '%$search%' OR p.clinic_number LIKE '%$search%' 
              OR ar.reason_name LIKE '%$search%' OR wa.ward_bed LIKE '%$search%')";
}

$sql .= " ORDER BY wa.admission_date DESC, wa.admission_time DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Admissions</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
        }
        
        .navbar {
            background: rgba(44, 62, 80, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar h1 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background-color: #34495e;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-header h2 {
            color: #2c3e50;
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .search-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .data-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-sm {
            padding: 0.4rem 0.6rem;
            font-size: 0.75rem;
            min-width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-group {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
            flex-wrap: nowrap;
        }
        
        .search-box {
            margin: 0;
            display: flex;
            gap: 1rem;
        }
        
        .search-box input {
            padding: 0.7rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            flex: 1;
            font-size: 1.05rem;
            min-width: 280px;
            max-width: 450px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        table thead {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
        }
        
        table th, table td {
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.95rem;
        }
        
        table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            transform: scale(1.01);
            transition: all 0.3s ease;
        }
        
        .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            white-space: nowrap;
        }
        
        .status-active {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }
        
        .status-discharged {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .status-transferred {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Tooltip styles */
        .btn[title] {
            position: relative;
        }
        
        .btn[title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            white-space: nowrap;
            font-size: 0.875rem;
            z-index: 1000;
            margin-bottom: 5px;
        }
        
        .btn[title]:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Ward Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="bed_management.php">🛏️ Bed Management</a>
            <a href="admission_reasons_list.php">Admission Reasons</a>
            <a href="death_registration.php">⚰️ Register Death</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <div>
                <h2>🏨 Ward Admissions</h2>
                <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Manage patient admissions to Ward</p>
            </div>
            <div class="quick-actions">
                <a href="admission_form.php" class="btn btn-primary">➕ New Admission</a>
                <a href="bed_management.php" class="btn btn-secondary">🛏️ Bed Management</a>
                <a href="admission_reasons_list.php" class="btn btn-secondary">🔧 Manage Reasons</a>
            </div>
        </div>
        
        <?php if (isset($_GET['deleted'])): ?>
            <?php if ($_GET['deleted'] == 'success'): ?>
                <div class="alert alert-success">Admission deleted successfully!</div>
            <?php elseif ($_GET['deleted'] == 'error'): ?>
                <div class="alert alert-danger">Error deleting admission. Please try again.</div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Bed Availability Quick View -->
        <?php
        $bed_stats = ['total_beds' => 0, 'available_beds' => 0, 'occupied_beds' => 0];
        
        // Try to get bed statistics, with error handling
        try {
            $bed_stats_query = "SELECT 
                COUNT(*) as total_beds,
                SUM(CASE WHEN bed_status = 'Available' THEN 1 ELSE 0 END) as available_beds,
                SUM(CASE WHEN bed_status = 'Occupied' THEN 1 ELSE 0 END) as occupied_beds
            FROM ward_beds WHERE is_active = 1";
            $bed_stats_result = $conn->query($bed_stats_query);
            if ($bed_stats_result) {
                $bed_stats = $bed_stats_result->fetch_assoc();
            }
        } catch (Exception $e) {
            // If table doesn't exist or query fails, use default values
            $bed_stats = ['total_beds' => 0, 'available_beds' => 0, 'occupied_beds' => 0];
        }
        ?>
        
        <div style="background: white; padding: 1rem; margin-bottom: 1rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 2rem; align-items: center;">
                    <div>
                        <span style="font-size: 0.9rem; color: #666;">🛏️ Bed Status:</span>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 12px; height: 12px; background: #27ae60; border-radius: 50%;"></div>
                            <span style="font-size: 0.9rem; color: #27ae60; font-weight: 600;">Available: <?php echo $bed_stats['available_beds']; ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 12px; height: 12px; background: #e74c3c; border-radius: 50%;"></div>
                            <span style="font-size: 0.9rem; color: #e74c3c; font-weight: 600;">Occupied: <?php echo $bed_stats['occupied_beds']; ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 12px; height: 12px; background: #4a90e2; border-radius: 50%;"></div>
                            <span style="font-size: 0.9rem; color: #4a90e2; font-weight: 600;">Total: <?php echo $bed_stats['total_beds']; ?></span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="bed_management.php" style="background: #4a90e2; color: white; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem;">
                        🛏️ Manage Beds
                    </a>
                </div>
            </div>
        </div>
        
        <div class="search-section">
            <div style="position:relative; max-width: 480px; margin: 0 auto;">
                <form class="filter-form" onsubmit="return false;">
                    <div class="filter-row search-box">
                        <input id="admissionSearchTerm" type="text" placeholder="Search admission by patient name, NIC, hospital number, reason, bed...">
                        <button id="admissionClearBtn" class="btn btn-primary" type="button">Clear</button>
                    </div>
                </form>
                <div id="admissionSearchResults" class="results"></div>
            </div>
        </div>
        <style>
        #admissionSearchResults {
            margin-top: 0;
            max-height: 350px;
            overflow-y: auto;
            background: #fff;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 24px rgba(44,62,80,0.12);
            border: 1px solid #e9ecef;
            border-top: none;
            padding: 0.5rem 0;
            z-index: 100;
            position: absolute;
            width: 100%;
            min-width: 0;
            top: 100%;
            left: 0;
        }
        .autocomplete-card {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            color: #222;
        }
        .autocomplete-card:last-child {
            border-bottom: none;
        }
        .autocomplete-card:hover {
            background: #f5faff;
        }
        .autocomplete-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .autocomplete-sub {
            font-size: 0.98rem;
            color: #555;
        }
        .autocomplete-meta {
            font-size: 0.88rem;
            color: #888;
            margin-top: 0.1rem;
        }
        .muted {
            color: #aaa;
            padding: 0.75rem 1.25rem;
        }
        </style>
        <script>
        const admissionSearchInput = document.getElementById('admissionSearchTerm');
        const admissionResults = document.getElementById('admissionSearchResults');
        let admissionTo;
        // Position results absolutely below the input
        // No need to set position absolute in JS, handled by CSS and new wrapper
        admissionSearchInput.addEventListener('input', ()=>{
            clearTimeout(admissionTo);
            const t = admissionSearchInput.value.trim();
            if (!t || t.length<2) { admissionResults.innerHTML=''; return; }
            admissionTo = setTimeout(()=>{
                fetch(`admission_list.php?search_admissions=1&search_term=${encodeURIComponent(t)}`)
                    .then(r=>r.json())
                    .then(data=>{
                        if (!data || data.length==0) { admissionResults.innerHTML='<div class="muted">No patients found</div>'; return; }
                        admissionResults.innerHTML = data.map(a=>{
                            return `<a class="autocomplete-card" data-id="${a.admission_id}" href="?search=${encodeURIComponent(a.calling_name)}">
                                <div class="autocomplete-title">${escapeHtml(a.calling_name)} <span style="font-weight:400; color:#888;">(${escapeHtml(a.full_name)})</span></div>
                                <div class="autocomplete-sub">NIC: <b>${escapeHtml(a.nic)}</b> &nbsp;|&nbsp; PHN: <b>${escapeHtml(a.hospital_number||'-')}</b></div>
                                <div class="autocomplete-meta">Reason: ${escapeHtml(a.reason_name||'-')} &nbsp;|&nbsp; Bed: ${escapeHtml(a.ward_bed||'-')}</div>
                            </a>`;
                        }).join('');
                        admissionResults.scrollTop = 0;
                    })
                    .catch(err=>{ console.error(err); admissionResults.innerHTML='<div class="muted">Search error</div>'; });
            },250);
        });
        document.getElementById('admissionClearBtn').addEventListener('click', ()=>{ admissionSearchInput.value=''; admissionResults.innerHTML=''; });
        function escapeHtml(s){ if(!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        </script>
        
        <div class="data-section">
            <div class="section-header" style="margin-bottom: 1.5rem;">
                <h3 style="color: #2c3e50; margin: 0; font-size: 1.25rem; font-weight: 600;">📋 Ward Admissions (<?php echo $result->num_rows; ?> records)</h3>
            </div>
            <div class="table-container" style="overflow-x: auto;">
                <?php if ($result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Admission ID</th>
                                <th>Patient Info</th>
                                <th>Admission Reason</th>
                                <th>Ward Bed</th>
                                <th>Admission Date/Time</th>
                                <th>Discharge Date/Time</th>
                                <th>Doctor/Nursing</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($admission = $result->fetch_assoc()): ?>
                                <?php
                                $death_row = false;
                                if (isset($admission['patient_id'])) {
                                    $patient_id = $admission['patient_id'];
                                    $patient_q = $conn->query("SELECT patient_status FROM patients WHERE patient_id = " . intval($patient_id));
                                    if ($patient_q && $patient_row = $patient_q->fetch_assoc()) {
                                        if (isset($patient_row['patient_status']) && strtolower($patient_row['patient_status']) === 'deceased') {
                                            $death_row = true;
                                        }
                                    }
                                }
                                ?>
                                <tr<?php if ($death_row) echo ' style="background: #ffeaea !important;"'; ?>>
                                    <td><span style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600;">#<?php echo str_pad($admission['admission_id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                    <td>
                                        <div style="font-weight: 600; color: #2c3e50;"><?php echo $admission['calling_name']; ?></div>
                                        <div style="font-size: 0.875rem; color: #7f8c8d;"><?php echo $admission['full_name']; ?></div>
                                        <div style="font-size: 0.8rem; color: #999;">NIC: <?php echo $admission['nic']; ?></div>
                                        <?php
                                        // Show death date if patient is deceased
                                        if (isset($admission['patient_id'])) {
                                            $patient_id = $admission['patient_id'];
                                            $patient_q = $conn->query("SELECT patient_status, death_date FROM patients WHERE patient_id = " . intval($patient_id));
                                            if ($patient_q && $patient_row = $patient_q->fetch_assoc()) {
                                                if (isset($patient_row['patient_status']) && strtolower($patient_row['patient_status']) === 'deceased' && !empty($patient_row['death_date'])) {
                                                    echo '<div style="color:#c0392b;font-size:0.75rem;margin-top:2px;">Death: ' . htmlspecialchars(date('Y-m-d', strtotime($patient_row['death_date']))) . '</div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td style="color: #555; font-weight: 500;"><?php echo $admission['reason_name']; ?></td>
                                    <td>
                                        <?php if ($admission['ward_bed']): ?>
                                            <span style="background: #e8f5e8; color: #2d6a2d; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 600;"><?php echo $admission['ward_bed']; ?></span>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($admission['admission_date'])); ?></div>
                                        <div style="font-size: 0.875rem; color: #777;"><?php echo date('g:i A', strtotime($admission['admission_time'])); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($admission['discharge_date']): ?>
                                            <div><?php echo date('M j, Y', strtotime($admission['discharge_date'])); ?></div>
                                            <?php if ($admission['discharge_time']): ?>
                                                <div style="font-size: 0.875rem; color: #777;"><?php echo date('g:i A', strtotime($admission['discharge_time'])); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">Not discharged</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.875rem;">
                                            <strong>Dr:</strong> 
                                            <?php 
                                            if ($admission['doctor_name']) {
                                                echo $admission['doctor_name'];
                                                if (!empty($admission['specialization'])) {
                                                    echo '<br><span style="color: #999; font-size: 0.8rem;">(' . $admission['specialization'] . ')</span>';
                                                }
                                            } else {
                                                echo 'Not assigned';
                                            }
                                            ?><br>
                                            <strong>Nurse:</strong> <?php echo $admission['nursing_name'] ? $admission['nursing_name'] : 'Not assigned'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        switch($admission['admission_status']) {
                                            case 'Active':
                                                $status_class = 'status-active';
                                                break;
                                            case 'Discharged':
                                                $status_class = 'status-discharged';
                                                break;
                                            case 'Transferred':
                                                $status_class = 'status-transferred';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $admission['admission_status']; ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="admission_view.php?id=<?php echo $admission['admission_id']; ?>" class="btn btn-info btn-sm" title="View">👁️</a>
                                            <?php if ($is_admin): ?>
                                                <a href="delete_admission.php?id=<?php echo $admission['admission_id']; ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Are you sure you want to delete admission #<?php echo str_pad($admission['admission_id'], 4, '0', STR_PAD_LEFT); ?>?');">🗑️</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #6c757d;">
                        <h3>🏨 No Admissions Found</h3>
                        <p style="margin: 1rem 0;">Start by creating your first ward admission.</p>
                        <a href="admission_form.php" class="btn btn-primary" style="margin-top: 1rem;">➕ Create First Admission</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>