<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Initialize filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$gender = isset($_GET['gender']) ? $_GET['gender'] : '';
$age_from = isset($_GET['age_from']) ? $_GET['age_from'] : '';
$age_to = isset($_GET['age_to']) ? $_GET['age_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build SQL query
$sql = "SELECT p.*, no.nursing_name 
        FROM patients p 
        LEFT JOIN nursing_officers no ON p.assigned_nursing_officer = no.nursing_id
        WHERE p.patient_status = 'Deceased'";

// Apply filters
if (!empty($date_from)) {
    $date_from_escaped = $conn->real_escape_string($date_from);
    $sql .= " AND p.death_date >= '$date_from_escaped'";
}

if (!empty($date_to)) {
    $date_to_escaped = $conn->real_escape_string($date_to);
    $sql .= " AND p.death_date <= '$date_to_escaped'";
}

if (!empty($gender)) {
    $gender_escaped = $conn->real_escape_string($gender);
    $sql .= " AND p.sex = '$gender_escaped'";
}

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $sql .= " AND (p.calling_name LIKE '%$search_escaped%' 
              OR p.full_name LIKE '%$search_escaped%' 
              OR p.nic LIKE '%$search_escaped%' 
              OR p.hospital_number LIKE '%$search_escaped%' 
              OR p.clinic_number LIKE '%$search_escaped%')";
}

// Age filter (calculate from date_of_birth)
$having_clause = '';
if (!empty($age_from) || !empty($age_to)) {
    $sql .= ", TIMESTAMPDIFF(YEAR, p.date_of_birth, p.death_date) as age_at_death";
    if (!empty($age_from) && !empty($age_to)) {
        $having_clause = " HAVING age_at_death BETWEEN " . (int)$age_from . " AND " . (int)$age_to;
    } elseif (!empty($age_from)) {
        $having_clause = " HAVING age_at_death >= " . (int)$age_from;
    } elseif (!empty($age_to)) {
        $having_clause = " HAVING age_at_death <= " . (int)$age_to;
    }
}

$sql .= " ORDER BY p.death_date DESC" . $having_clause;

$result = $conn->query($sql);

// Export to CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="death_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Patient ID', 'Calling Name', 'Full Name', 'NIC', 'Gender', 'Date of Birth', 'Age at Death', 'Death Date', 'Death Notes', 'Nursing Officer', 'Contact Number'));
    
    if ($result->num_rows > 0) {
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            $dob = new DateTime($row['date_of_birth']);
            $death_date = new DateTime($row['death_date']);
            $age_at_death = $death_date->diff($dob)->y;
            
            fputcsv($output, array(
                $row['patient_id'],
                $row['calling_name'],
                $row['full_name'],
                $row['nic'],
                $row['sex'],
                date('M j, Y', strtotime($row['date_of_birth'])),
                $age_at_death,
                date('M j, Y', strtotime($row['death_date'])),
                $row['death_notes'],
                $row['nursing_name'] ? $row['nursing_name'] : 'Not assigned',
                $row['contact_number']
            ));
        }
    }
    
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Death Report - Hospital Management System</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .card-header h2 {
            font-size: 2rem;
            margin: 0 0 0.5rem 0;
        }
        
        .card-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .filters {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
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
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(44, 62, 80, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            border-left: 4px solid #d32f2f;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            color: #c62828;
            margin: 0 0 0.5rem 0;
        }
        
        .stat-card p {
            color: #2c3e50;
            font-weight: 500;
            margin: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        table thead {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
        }
        
        table th,
        table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        table tbody tr {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            transition: all 0.3s ease;
        }
        
        table tbody tr:hover {
            background: linear-gradient(135deg, #ffcdd2, #ef9a9a);
            transform: scale(1.01);
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        @media print {
            .navbar, .filters, .button-group, .btn {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Hospital Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="report_list.php">Reports</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>⚰️ Death Report</h2>
                <p>Comprehensive deceased patient records with advanced filtering</p>
            </div>
            
            <div class="card-body">
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo $result->num_rows; ?></h3>
                        <p>Total Deceased Patients</p>
                    </div>
                    <?php if (!empty($date_from) && !empty($date_to)): ?>
                    <div class="stat-card">
                        <h3><?php echo date('M j, Y', strtotime($date_from)); ?></h3>
                        <p>From Date</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo date('M j, Y', strtotime($date_to)); ?></h3>
                        <p>To Date</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <h3 style="margin-bottom: 1rem; color: #2c3e50;">🔍 Filters</h3>
                    <form method="GET" action="">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="date_from">Death Date From</label>
                                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="form-group">
                                <label for="date_to">Death Date To</label>
                                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender">
                                    <option value="">All Genders</option>
                                    <option value="Male" <?php echo $gender == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $gender == 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="age_from">Age at Death From</label>
                                <input type="number" id="age_from" name="age_from" value="<?php echo htmlspecialchars($age_from); ?>" placeholder="0" min="0">
                            </div>
                            <div class="form-group">
                                <label for="age_to">Age at Death To</label>
                                <input type="number" id="age_to" name="age_to" value="<?php echo htmlspecialchars($age_to); ?>" placeholder="100" min="0">
                            </div>
                            <div class="form-group">
                                <label for="search">Search Patient</label>
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, NIC, Hospital #...">
                            </div>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                            <a href="death_report.php" class="btn btn-secondary">🔄 Reset Filters</a>
                            <a href="?export=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-success">📥 Export to CSV</a>
                            <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Print Report</button>
                        </div>
                    </form>
                </div>
                
                <!-- Data Table -->
                <?php if ($result->num_rows > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient Name</th>
                                    <th>NIC</th>
                                    <th>Gender</th>
                                    <th>Date of Birth</th>
                                    <th>Age at Death</th>
                                    <th>Death Date</th>
                                    <th>Death Notes</th>
                                    <th>Nursing Officer</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($patient = $result->fetch_assoc()): 
                                    $dob = new DateTime($patient['date_of_birth']);
                                    $death_date = new DateTime($patient['death_date']);
                                    $age_at_death = $death_date->diff($dob)->y;
                                ?>
                                    <tr>
                                        <td><strong><?php echo $patient['patient_id']; ?></strong></td>
                                        <td>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($patient['calling_name']); ?></div>
                                            <div style="font-size: 0.875rem; color: #7f8c8d;"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['nic']); ?></td>
                                        <td><?php echo htmlspecialchars($patient['sex']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?></td>
                                        <td><strong><?php echo $age_at_death; ?> years</strong></td>
                                        <td style="font-weight: 600; color: #c62828;"><?php echo date('M j, Y', strtotime($patient['death_date'])); ?></td>
                                        <td><?php echo $patient['death_notes'] ? nl2br(htmlspecialchars($patient['death_notes'])) : '<span style="color: #999;">-</span>'; ?></td>
                                        <td><?php echo $patient['nursing_name'] ? htmlspecialchars($patient['nursing_name']) : '<span style="color: #999;">Not assigned</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($patient['contact_number']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <h3>⚰️ No Deceased Patients Found</h3>
                        <p style="margin: 1rem 0;">Try adjusting your filters or check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
