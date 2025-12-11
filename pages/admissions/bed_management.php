<?php
include '../../config/db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Create beds table if it doesn't exist
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
$conn->query($create_beds_table);

// Handle success messages from redirects
$message = '';
$message_type = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'bed_added':
            $message = "Bed added successfully!";
            $message_type = 'success';
            break;
        case 'status_updated':
            $message = "Bed status updated successfully!";
            $message_type = 'success';
            break;
    }
}

// Handle refresh action - redirect to clean URL
if (isset($_GET['refresh'])) {
    header("Location: bed_management.php");
    exit();
}

// Handle bed actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_bed':
                $ward_name = $conn->real_escape_string($_POST['ward_name']);
                $bed_number = $conn->real_escape_string($_POST['bed_number']);
                $bed_type = $conn->real_escape_string($_POST['bed_type']);
                $room_number = $conn->real_escape_string($_POST['room_number']);
                $floor_number = $conn->real_escape_string($_POST['floor_number']);
                $equipment = $conn->real_escape_string($_POST['equipment']);
                $notes = $conn->real_escape_string($_POST['notes']);
                
                // Check if bed already exists
                $check_sql = "SELECT bed_id FROM ward_beds WHERE ward_name = '$ward_name' AND bed_number = '$bed_number'";
                $check_result = $conn->query($check_sql);
                
                if ($check_result && $check_result->num_rows > 0) {
                    $message = "Error: Bed '$ward_name - $bed_number' already exists! Please use a different bed number.";
                    $message_type = 'error';
                } else {
                    $insert_sql = "INSERT INTO ward_beds (ward_name, bed_number, bed_type, room_number, floor_number, equipment, notes) 
                                  VALUES ('$ward_name', '$bed_number', '$bed_type', '$room_number', '$floor_number', '$equipment', '$notes')";
                    
                    if ($conn->query($insert_sql)) {
                        // Redirect to prevent form resubmission and clear messages
                        header("Location: bed_management.php?success=bed_added");
                        exit();
                    } else {
                        // Handle any other database errors
                        if (strpos($conn->error, 'Duplicate entry') !== false) {
                            $message = "Error: Bed '$ward_name - $bed_number' already exists! Please use a different bed number.";
                        } else {
                            $message = "Error adding bed: " . $conn->error;
                        }
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'update_bed_status':
                $bed_id = (int)$_POST['bed_id'];
                $new_status = $conn->real_escape_string($_POST['new_status']);
                
                $update_sql = "UPDATE ward_beds SET bed_status = '$new_status' WHERE bed_id = $bed_id";
                
                if ($conn->query($update_sql)) {
                    // Redirect to prevent form resubmission
                    header("Location: bed_management.php?success=status_updated");
                    exit();
                } else {
                    $message = "Error updating bed status: " . $conn->error;
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get bed statistics
$stats_query = "SELECT 
    COUNT(*) as total_beds,
    SUM(CASE WHEN bed_status = 'Available' THEN 1 ELSE 0 END) as available_beds,
    SUM(CASE WHEN bed_status = 'Occupied' THEN 1 ELSE 0 END) as occupied_beds,
    SUM(CASE WHEN bed_status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance_beds,
    SUM(CASE WHEN bed_status = 'Reserved' THEN 1 ELSE 0 END) as reserved_beds,
    ROUND((SUM(CASE WHEN bed_status = 'Occupied' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as occupancy_rate
FROM ward_beds WHERE is_active = 1";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get current bed occupancy with patient details
$occupancy_query = "SELECT 
    wb.bed_id, wb.ward_name, wb.bed_number, wb.bed_type, wb.room_number, wb.floor_number,
    wb.bed_status, wb.equipment, wb.notes,
    wa.admission_id, wa.admission_date, wa.admission_time,
    p.calling_name, p.full_name, p.nic, p.contact_number,
    d.doctor_name, no.nursing_name,
    ar.reason_name
FROM ward_beds wb
LEFT JOIN ward_admissions wa ON CONCAT(wb.ward_name, ' - ', wb.bed_number) = wa.ward_bed 
    AND wa.admission_status = 'Active'
LEFT JOIN patients p ON wa.patient_id = p.patient_id
LEFT JOIN doctors d ON wa.attending_doctor_id = d.doctor_id
LEFT JOIN nursing_officers no ON wa.nursing_officer_id = no.nursing_id
LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
WHERE wb.is_active = 1
ORDER BY wb.ward_name, wb.bed_number";

$beds_result = $conn->query($occupancy_query);

// Update bed status based on actual admissions
$sync_query = "UPDATE ward_beds wb
SET bed_status = CASE 
    WHEN EXISTS (
        SELECT 1 FROM ward_admissions wa 
        WHERE CONCAT(wb.ward_name, ' - ', wb.bed_number) = wa.ward_bed 
        AND wa.admission_status = 'Active'
    ) THEN 'Occupied'
    WHEN bed_status = 'Occupied' AND NOT EXISTS (
        SELECT 1 FROM ward_admissions wa 
        WHERE CONCAT(wb.ward_name, ' - ', wb.bed_number) = wa.ward_bed 
        AND wa.admission_status = 'Active'
    ) THEN 'Available'
    ELSE bed_status
END
WHERE is_active = 1";

$conn->query($sync_query);

// Get ward distribution
$ward_stats_query = "SELECT 
    ward_name,
    COUNT(*) as total_beds,
    SUM(CASE WHEN bed_status = 'Available' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN bed_status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
    SUM(CASE WHEN bed_status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance,
    SUM(CASE WHEN bed_status = 'Reserved' THEN 1 ELSE 0 END) as reserved
FROM ward_beds 
WHERE is_active = 1 
GROUP BY ward_name 
ORDER BY ward_name";

$ward_stats_result = $conn->query($ward_stats_query);

include '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Bed Management - CHHMS</title>
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            color: #4a90e2;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-links a {
            color: #4a90e2;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-links a:hover {
            background: #4a90e2;
            color: white;
            transform: translateY(-2px);
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h2 {
            color: white;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-total { color: #4a90e2; }
        .stat-available { color: #27ae60; }
        .stat-occupied { color: #e74c3c; }
        .stat-maintenance { color: #f39c12; }
        .stat-reserved { color: #9b59b6; }
        .stat-occupancy { color: #34495e; }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .beds-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .add-bed-btn {
            background: #27ae60;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-bed-btn:hover {
            background: #229954;
            transform: translateY(-2px);
        }

        .beds-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            max-height: 600px;
            overflow-y: auto;
        }

        .bed-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .bed-card.available { 
            border-color: #27ae60; 
            background: linear-gradient(135deg, #d5f4e6 0%, #fafafa 100%);
        }

        .bed-card.occupied { 
            border-color: #e74c3c; 
            background: linear-gradient(135deg, #fadbd8 0%, #fafafa 100%);
        }

        .bed-card.maintenance { 
            border-color: #f39c12; 
            background: linear-gradient(135deg, #fdebd0 0%, #fafafa 100%);
        }

        .bed-card.reserved { 
            border-color: #9b59b6; 
            background: linear-gradient(135deg, #e8daef 0%, #fafafa 100%);
        }

        .bed-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .bed-info h4 {
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .bed-type {
            font-size: 0.8rem;
            color: #666;
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-available { background: #d5f4e6; color: #27ae60; }
        .status-occupied { background: #fadbd8; color: #e74c3c; }
        .status-maintenance { background: #fdebd0; color: #f39c12; }
        .status-reserved { background: #e8daef; color: #9b59b6; }

        .patient-info {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .patient-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .patient-details {
            font-size: 0.85rem;
            color: #666;
            line-height: 1.4;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .ward-stats {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .ward-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .ward-item:hover {
            background: #e9ecef;
        }

        .ward-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .ward-counts {
            display: flex;
            gap: 0.5rem;
        }

        .count-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .add-bed-form {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4a90e2;
        }

        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #4a90e2;
            color: white;
        }

        .btn-primary:hover {
            background: #357abd;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .message.success {
            background: #d5f4e6;
            color: #27ae60;
            border: 1px solid #27ae60;
        }

        .message.error {
            background: #fadbd8;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }

        .status-actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .status-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .beds-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🏥 Ward Bed Management System</h1>
        <div class="nav-links">
            <a href="../../index.php">🏠 Dashboard</a>
            <a href="admission_list.php">📋 Admissions</a>
            <a href="../../logout.php">🚪 Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>🛏️ Ward Bed Management</h2>
            <p>Monitor and manage hospital bed allocation and occupancy</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number stat-total"><?php echo $stats['total_beds'] ?: 0; ?></div>
                <div class="stat-label">Total Beds</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-available"><?php echo $stats['available_beds'] ?: 0; ?></div>
                <div class="stat-label">Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-occupied"><?php echo $stats['occupied_beds'] ?: 0; ?></div>
                <div class="stat-label">Occupied</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-maintenance"><?php echo $stats['maintenance_beds'] ?: 0; ?></div>
                <div class="stat-label">Maintenance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-reserved"><?php echo $stats['reserved_beds'] ?: 0; ?></div>
                <div class="stat-label">Reserved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-occupancy"><?php echo $stats['occupancy_rate'] ?: 0; ?>%</div>
                <div class="stat-label">Occupancy Rate</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Beds Grid -->
            <div class="beds-section">
                <div class="section-header">
                    <h3 class="section-title">🛏️ Hospital Beds</h3>
                    <button class="add-bed-btn" onclick="toggleAddBedForm()">
                        ➕ Add New Bed
                    </button>
                </div>
                
                <div class="beds-grid">
                    <?php if ($beds_result->num_rows > 0): ?>
                        <?php while ($bed = $beds_result->fetch_assoc()): ?>
                            <div class="bed-card <?php echo strtolower($bed['bed_status']); ?>">
                                <div class="bed-header">
                                    <div class="bed-info">
                                        <h4><?php echo htmlspecialchars($bed['ward_name'] . ' - ' . $bed['bed_number']); ?></h4>
                                        <span class="bed-type"><?php echo htmlspecialchars($bed['bed_type']); ?></span>
                                        <?php if ($bed['room_number']): ?>
                                            <span class="bed-type">Room: <?php echo htmlspecialchars($bed['room_number']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status-badge status-<?php echo strtolower($bed['bed_status']); ?>">
                                        <?php echo $bed['bed_status']; ?>
                                    </span>
                                </div>

                                <?php if ($bed['admission_id']): ?>
                                    <div class="patient-info">
                                        <div class="patient-name">
                                            👤 <?php echo htmlspecialchars($bed['calling_name']); ?>
                                        </div>
                                        <div class="patient-details">
                                            <strong>NIC:</strong> <?php echo htmlspecialchars($bed['nic']); ?><br>
                                            <strong>Admitted:</strong> <?php echo date('M j, Y g:i A', strtotime($bed['admission_date'] . ' ' . $bed['admission_time'])); ?><br>
                                            <strong>Doctor:</strong> <?php echo htmlspecialchars($bed['doctor_name'] ?: 'Not assigned'); ?><br>
                                            <strong>Reason:</strong> <?php echo htmlspecialchars($bed['reason_name'] ?: 'Not specified'); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($bed['equipment']): ?>
                                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #e9ecef;">
                                        <small><strong>Equipment:</strong> <?php echo htmlspecialchars($bed['equipment']); ?></small>
                                    </div>
                                <?php endif; ?>

                                <div class="status-actions">
                                    <form method="POST" style="display: flex; gap: 0.5rem;">
                                        <input type="hidden" name="action" value="update_bed_status">
                                        <input type="hidden" name="bed_id" value="<?php echo $bed['bed_id']; ?>">
                                        <select name="new_status" class="status-select" onchange="this.form.submit()">
                                            <option value="Available" <?php echo $bed['bed_status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                                            <option value="Occupied" <?php echo $bed['bed_status'] == 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                                            <option value="Maintenance" <?php echo $bed['bed_status'] == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                            <option value="Reserved" <?php echo $bed['bed_status'] == 'Reserved' ? 'selected' : ''; ?>>Reserved</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: #666;">
                            <h3>No beds configured</h3>
                            <p>Click "Add New Bed" to start setting up your ward beds</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Ward Statistics -->
                <div class="ward-stats">
                    <h3 class="section-title">📊 Ward Distribution</h3>
                    <?php if ($ward_stats_result->num_rows > 0): ?>
                        <?php while ($ward = $ward_stats_result->fetch_assoc()): ?>
                            <div class="ward-item">
                                <div class="ward-name"><?php echo htmlspecialchars($ward['ward_name']); ?></div>
                                <div class="ward-counts">
                                    <span class="count-badge" style="background: #d5f4e6; color: #27ae60;">
                                        A: <?php echo $ward['available']; ?>
                                    </span>
                                    <span class="count-badge" style="background: #fadbd8; color: #e74c3c;">
                                        O: <?php echo $ward['occupied']; ?>
                                    </span>
                                    <span class="count-badge" style="background: #fdebd0; color: #f39c12;">
                                        M: <?php echo $ward['maintenance']; ?>
                                    </span>
                                    <span class="count-badge" style="background: #e8daef; color: #9b59b6;">
                                        R: <?php echo $ward['reserved']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #666; text-align: center; padding: 1rem;">No wards configured yet</p>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="ward-stats">
                    <h3 class="section-title">⚡ Quick Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="admission_form.php" class="add-bed-btn">👤 New Admission</a>
                        <a href="admission_list.php" class="add-bed-btn" style="background: #4a90e2;">📋 View Admissions</a>
                        <button class="add-bed-btn" style="background: #f39c12;" onclick="refreshBeds()">🔄 Refresh Beds</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Bed Form -->
        <div id="addBedForm" class="add-bed-form">
            <h3 class="section-title">➕ Add New Bed</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_bed">
                
                <div class="form-group">
                    <label for="ward_name">Ward Name *</label>
                    <input type="text" id="ward_name" name="ward_name" required placeholder="e.g., General Ward, ICU, CCU">
                </div>
                
                <div class="form-group">
                    <label for="bed_number">Bed Number *</label>
                    <input type="text" id="bed_number" name="bed_number" required placeholder="e.g., A-01, B-15, ICU-03">
                </div>
                
                <div class="form-group">
                    <label for="bed_type">Bed Type</label>
                    <select id="bed_type" name="bed_type" required>
                        <option value="General">General</option>
                        <option value="ICU">ICU</option>
                        <option value="CCU">CCU</option>
                        <option value="Private">Private</option>
                        <option value="Semi-Private">Semi-Private</option>
                        <option value="Emergency">Emergency</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="room_number">Room Number</label>
                    <input type="text" id="room_number" name="room_number" placeholder="e.g., 101, 205A">
                </div>
                
                <div class="form-group">
                    <label for="floor_number">Floor</label>
                    <input type="text" id="floor_number" name="floor_number" placeholder="e.g., Ground, 1st, 2nd">
                </div>
                
                <div class="form-group">
                    <label for="equipment">Equipment/Features</label>
                    <textarea id="equipment" name="equipment" rows="3" placeholder="e.g., Ventilator, Oxygen supply, Monitor"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Additional notes or special instructions"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">💾 Save Bed</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleAddBedForm()">❌ Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Get existing beds for validation
        const existingBeds = [
            <?php
            $existing_beds_query = "SELECT ward_name, bed_number FROM ward_beds WHERE is_active = 1";
            $existing_beds_result = $conn->query($existing_beds_query);
            $bed_combinations = [];
            if ($existing_beds_result) {
                while ($existing_bed = $existing_beds_result->fetch_assoc()) {
                    $bed_combinations[] = '"' . addslashes($existing_bed['ward_name']) . ' - ' . addslashes($existing_bed['bed_number']) . '"';
                }
            }
            echo implode(', ', $bed_combinations);
            ?>
        ];

        function toggleAddBedForm() {
            const form = document.getElementById('addBedForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            
            // Clear form when opening
            if (form.style.display === 'block') {
                form.querySelector('form').reset();
            }
        }

        function refreshBeds() {
            // Use a clean refresh by redirecting to the page without any parameters
            window.location.href = window.location.pathname;
        }

        // Validate bed combination before submission
        document.addEventListener('DOMContentLoaded', function() {
            const bedForm = document.querySelector('#addBedForm form');
            if (bedForm) {
                bedForm.addEventListener('submit', function(e) {
                    const wardName = document.getElementById('ward_name').value.trim();
                    const bedNumber = document.getElementById('bed_number').value.trim();
                    const bedCombination = wardName + ' - ' + bedNumber;
                    
                    if (existingBeds.includes(bedCombination)) {
                        e.preventDefault();
                        alert(`Error: Bed "${bedCombination}" already exists! Please use a different ward name or bed number.`);
                        return false;
                    }
                });
            }
        });

        // Auto-refresh every 30 seconds (clean refresh)
        setInterval(() => {
            window.location.href = window.location.pathname;
        }, 30000);
    </script>
</body>
</html>
<?php include '../../includes/footer.php'; ?>