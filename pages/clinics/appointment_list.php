<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Cancel appointment if requested
if (isset($_GET['cancel'])) {
    $id = $conn->real_escape_string($_GET['cancel']);
    $sql = "UPDATE clinic_appointments SET appointment_status = 'Cancelled' WHERE appointment_id = $id";
    if ($conn->query($sql) === TRUE) {
        header("Location: appointment_list.php?cancelled=success");
        exit();
    } else {
        header("Location: appointment_list.php?cancelled=error");
        exit();
    }
}

// Update appointment status if requested
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $status = $conn->real_escape_string($_GET['status']);
    $valid_statuses = ['Confirmed', 'In Progress', 'Completed', 'No Show'];
    
    if (in_array($status, $valid_statuses)) {
        $sql = "UPDATE clinic_appointments SET appointment_status = '$status' WHERE appointment_id = $id";
        if ($conn->query($sql) === TRUE) {
            header("Location: appointment_list.php?updated=success");
            exit();
        } else {
            header("Location: appointment_list.php?updated=error");
            exit();
        }
    }
}

// Search and filter functionality
$search = '';
$clinic_filter = '';
$date_filter = '';
$status_filter = '';

$sql = "SELECT ca.*, 
               c.clinic_name, c.clinic_code,
               p.calling_name, p.full_name, p.nic, p.contact_number,
               u.username as created_by_name
        FROM clinic_appointments ca
        LEFT JOIN clinics c ON ca.clinic_id = c.clinic_id
        LEFT JOIN patients p ON ca.patient_id = p.patient_id
        LEFT JOIN users u ON ca.created_by = u.user_id
        WHERE 1=1";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " AND (p.calling_name LIKE '%$search%' OR p.full_name LIKE '%$search%' OR p.nic LIKE '%$search%' OR c.clinic_name LIKE '%$search%')";
}

if (isset($_GET['clinic_filter']) && !empty($_GET['clinic_filter'])) {
    $clinic_filter = $conn->real_escape_string($_GET['clinic_filter']);
    $sql .= " AND ca.clinic_id = '$clinic_filter'";
}

if (isset($_GET['date_filter']) && !empty($_GET['date_filter'])) {
    $date_filter = $conn->real_escape_string($_GET['date_filter']);
    $sql .= " AND ca.appointment_date = '$date_filter'";
}

if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
    $status_filter = $conn->real_escape_string($_GET['status_filter']);
    $sql .= " AND ca.appointment_status = '$status_filter'";
}

$sql .= " ORDER BY ca.appointment_date DESC, ca.appointment_time DESC";
$result = $conn->query($sql);

// Get clinics for filter dropdown
$clinics_result = $conn->query("SELECT clinic_id, clinic_name, clinic_code FROM clinics WHERE is_active = 1 ORDER BY clinic_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAPD Clinic Appointments</title>
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
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
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
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60, #219a52);
            color: white;
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }
        
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .data-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            padding: 1.5rem 2rem;
            border-bottom: 3px solid #3498db;
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        th, td {
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-scheduled {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-in-progress {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-no-show {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .actions-group {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
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
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Ward Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">Dashboard</a>
            <a href="clinic_list.php">Clinics</a>
            <a href="../patients/patient_list.php">Patients</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <div>
                <h2>📅 CAPD Clinic Appointments</h2>
                <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Manage CAPD patient appointments and treatment schedules</p>
            </div>
            <div class="quick-actions">
                <a href="appointment_form.php" class="btn btn-primary">➕ New Appointment</a>
                <a href="clinic_list.php" class="btn btn-secondary">🏢 Manage Clinics</a>
            </div>
        </div>
        
        <?php if (isset($_GET['cancelled'])): ?>
            <?php if ($_GET['cancelled'] == 'success'): ?>
                <div class="alert alert-success">Appointment cancelled successfully!</div>
            <?php elseif ($_GET['cancelled'] == 'error'): ?>
                <div class="alert alert-danger">Error cancelling appointment. Please try again.</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_GET['updated'])): ?>
            <?php if ($_GET['updated'] == 'success'): ?>
                <div class="alert alert-success">Appointment status updated successfully!</div>
            <?php elseif ($_GET['updated'] == 'error'): ?>
                <div class="alert alert-danger">Error updating appointment status. Please try again.</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="filters-section">
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="search">Search Patient/Clinic</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Patient name, NIC, or clinic name...">
                    </div>
                    <div class="filter-group">
                        <label for="clinic_filter">Clinic</label>
                        <select id="clinic_filter" name="clinic_filter">
                            <option value="">All Clinics</option>
                            <?php while ($clinic = $clinics_result->fetch_assoc()): ?>
                                <option value="<?php echo $clinic['clinic_id']; ?>" <?php echo ($clinic_filter == $clinic['clinic_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($clinic['clinic_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date_filter">Date</label>
                        <input type="date" id="date_filter" name="date_filter" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status_filter">Status</label>
                        <select id="status_filter" name="status_filter">
                            <option value="">All Status</option>
                            <option value="Scheduled" <?php echo ($status_filter == 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="Confirmed" <?php echo ($status_filter == 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="In Progress" <?php echo ($status_filter == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo ($status_filter == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo ($status_filter == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="No Show" <?php echo ($status_filter == 'No Show') ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    </div>
                </div>
            </form>
            
            <?php if (!empty($search) || !empty($clinic_filter) || !empty($date_filter) || !empty($status_filter)): ?>
                <div style="margin-top: 1rem;">
                    <a href="appointment_list.php" class="btn btn-secondary">✖️ Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="data-section">
            <div class="section-header">
                <h3>📋 Appointments (<?php echo $result->num_rows; ?> records)</h3>
            </div>
            
            <?php if ($result->num_rows > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient</th>
                                <th>Clinic</th>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Chief Complaint</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($appointment = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo str_pad($appointment['appointment_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($appointment['calling_name']); ?></div>
                                        <small><?php echo htmlspecialchars($appointment['full_name']); ?></small><br>
                                        <small>NIC: <?php echo htmlspecialchars($appointment['nic']); ?></small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($appointment['clinic_name']); ?></div>
                                        <small><?php echo htmlspecialchars($appointment['clinic_code']); ?></small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                        <small><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['appointment_type']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $appointment['appointment_status'])); ?>">
                                            <?php echo $appointment['appointment_status']; ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 200px;">
                                        <?php echo $appointment['chief_complaint'] ? htmlspecialchars($appointment['chief_complaint']) : '<em>No complaint specified</em>'; ?>
                                    </td>
                                    <td>
                                        <div class="actions-group">
                                            <?php if ($appointment['appointment_status'] == 'Scheduled'): ?>
                                                <a href="appointment_list.php?status=Confirmed&id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-success" title="Confirm">✓</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($appointment['appointment_status'] == 'Confirmed'): ?>
                                                <a href="appointment_list.php?status=In Progress&id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-warning" title="Start">▶</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($appointment['appointment_status'] == 'In Progress'): ?>
                                                <a href="appointment_list.php?status=Completed&id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-success" title="Complete">✓</a>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($appointment['appointment_status'], ['Scheduled', 'Confirmed'])): ?>
                                                <a href="appointment_list.php?cancel=<?php echo $appointment['appointment_id']; ?>" class="btn btn-danger" title="Cancel" onclick="return confirm('Cancel this appointment?');">✕</a>
                                            <?php endif; ?>
                                            
                                            <a href="appointment_form.php?edit=<?php echo $appointment['appointment_id']; ?>" class="btn btn-warning" title="Edit">✏️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>📅 No Appointments Found</h3>
                    <p>No appointments match your current filters.</p>
                    <a href="appointment_form.php" class="btn btn-primary" style="margin-top: 1rem;">➕ Schedule First Appointment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>