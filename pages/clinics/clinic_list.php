<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Create clinics table if it doesn't exist
$create_clinics_table = "CREATE TABLE IF NOT EXISTS clinics (
    clinic_id INT AUTO_INCREMENT PRIMARY KEY,
    clinic_name VARCHAR(255) NOT NULL,
    clinic_code VARCHAR(10) UNIQUE NOT NULL,
    department VARCHAR(100),
    location VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(100),
    operating_hours TEXT,
    consultant_doctor_id INT,
    max_appointments_per_day INT DEFAULT 50,
    appointment_duration INT DEFAULT 15,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (consultant_doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL
)";
$conn->query($create_clinics_table);

// Create appointments table if it doesn't exist
$create_appointments_table = "CREATE TABLE IF NOT EXISTS clinic_appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    patient_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    appointment_status ENUM('Scheduled', 'Confirmed', 'In Progress', 'Completed', 'Cancelled', 'No Show') DEFAULT 'Scheduled',
    appointment_type VARCHAR(50) DEFAULT 'Regular',
    chief_complaint TEXT,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (clinic_id) REFERENCES clinics(clinic_id),
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
)";
$conn->query($create_appointments_table);

// Insert default clinics if table is empty
$check_sql = "SELECT COUNT(*) as count FROM clinics";
$check_result = $conn->query($check_sql);
$count = $check_result->fetch_assoc()['count'];

if ($count == 0) {
    // Get first doctor for default assignment
    $doctor_result = $conn->query("SELECT doctor_id FROM doctors LIMIT 1");
    $doctor_id = $doctor_result->num_rows > 0 ? $doctor_result->fetch_assoc()['doctor_id'] : null;
    
    $default_clinics = [
        ['CAPD Clinic', 'CAPD01', 'Nephrology - CAPD Unit', 'Ground Floor - CAPD Wing', '011-2345100', 'capd@hospital.com', 'Mon-Fri: 7:00 AM - 5:00 PM, Sat: 8:00 AM - 12:00 PM', $doctor_id, 25, 30]
    ];
    
    foreach ($default_clinics as $clinic) {
        $insert_sql = "INSERT INTO clinics (clinic_name, clinic_code, department, location, phone, email, operating_hours, consultant_doctor_id, max_appointments_per_day, appointment_duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssssssiid", $clinic[0], $clinic[1], $clinic[2], $clinic[3], $clinic[4], $clinic[5], $clinic[6], $clinic[7], $clinic[8], $clinic[9]);
        $stmt->execute();
    }
}

// Get current user role
$current_user_sql = "SELECT user_role FROM users WHERE user_id = {$_SESSION['user_id']}";
$current_user_result = $conn->query($current_user_sql);
$current_user = $current_user_result->fetch_assoc();
$is_admin = ($current_user['user_role'] == 'Admin');

// Delete clinic if requested (only for Admin)
if (isset($_GET['delete']) && $is_admin) {
    $id = $conn->real_escape_string($_GET['delete']);
    $sql = "UPDATE clinics SET is_active = 0 WHERE clinic_id = $id";
    if ($conn->query($sql) === TRUE) {
        header("Location: clinic_list.php?deleted=success");
        exit();
    } else {
        header("Location: clinic_list.php?deleted=error");
        exit();
    }
}

// Search functionality
$search = '';
$sql = "SELECT c.*, d.doctor_name, d.specialization,
               COUNT(ca.appointment_id) as total_appointments,
               COUNT(CASE WHEN ca.appointment_date = CURDATE() THEN 1 END) as today_appointments
        FROM clinics c 
        LEFT JOIN doctors d ON c.consultant_doctor_id = d.doctor_id
        LEFT JOIN clinic_appointments ca ON c.clinic_id = ca.clinic_id AND ca.appointment_status NOT IN ('Cancelled')
        WHERE c.is_active = 1";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " AND (c.clinic_name LIKE '%$search%' OR c.clinic_code LIKE '%$search%' OR c.department LIKE '%$search%' OR c.location LIKE '%$search%')";
}

$sql .= " GROUP BY c.clinic_id ORDER BY c.clinic_name ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAPD Clinic Management</title>
    <?php
    // CAPD Clinic module removed
    include_once '../../includes/header.php';
    echo '<div class="container"><div class="card"><h3>CAPD Clinic Module Removed</h3><p>This module has been removed from the application.</p><a href="../../index.php" class="btn btn-secondary">← Back to Dashboard</a></div></div>';
    include_once '../../includes/footer.php';
    exit();
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
        
        .search-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .search-box {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-box input {
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            flex: 1;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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
        
        .clinic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
            padding: 2rem;
        }
        
        .clinic-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #3498db;
        }
        
        .clinic-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .clinic-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .clinic-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .clinic-code {
            background: #3498db;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .clinic-info {
            margin-bottom: 1rem;
        }
        
        .clinic-info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            font-size: 0.9rem;
        }
        
        .clinic-info-label {
            font-weight: 600;
            color: #555;
        }
        
        .clinic-info-value {
            color: #333;
            text-align: right;
            max-width: 60%;
        }
        
        .clinic-stats {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            border: 1px solid #e9ecef;
        }
        
        .stats-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .stats-row:last-child {
            margin-bottom: 0;
        }
        
        .stat-label {
            font-weight: 600;
            color: #666;
        }
        
        .stat-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .clinic-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1rem;
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
            .clinic-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
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
            <a href="appointment_list.php">Appointments</a>
            <a href="../patients/patient_list.php">Patients</a>
            <a href="../../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <div>
                <h2>🩺 CAPD Clinic Management</h2>
                <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Manage CAPD clinic and patient appointments</p>
            </div>
            <div class="quick-actions">
                <a href="appointment_form.php" class="btn btn-success">📅 New CAPD Appointment</a>
                <a href="appointment_list.php" class="btn btn-secondary">📋 CAPD Appointments</a>
            </div>
        </div>
        
        <?php if (isset($_GET['deleted'])): ?>
            <?php if ($_GET['deleted'] == 'success'): ?>
                <div class="alert alert-success">Clinic deactivated successfully!</div>
            <?php elseif ($_GET['deleted'] == 'error'): ?>
                <div class="alert alert-danger">Error deactivating clinic. Please try again.</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="search-section">
            <form style="margin: 0;">
                <div class="search-box">
                    <input type="text" name="search" placeholder="🔍 Search clinics by name, code, department, or location..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="clinic_list.php" class="btn btn-secondary">✖️ Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="data-section">
            <div class="section-header">
                <h3>🩺 CAPD Clinic Status</h3>
            </div>
            
            <?php if ($result->num_rows > 0): ?>
                <div class="clinic-grid">
                    <?php while ($clinic = $result->fetch_assoc()): ?>
                        <div class="clinic-card">
                            <div class="clinic-header">
                                <h4 class="clinic-name"><?php echo htmlspecialchars($clinic['clinic_name']); ?></h4>
                                <span class="clinic-code"><?php echo htmlspecialchars($clinic['clinic_code']); ?></span>
                            </div>
                            
                            <div class="clinic-info">
                                <div class="clinic-info-item">
                                    <span class="clinic-info-label">Department:</span>
                                    <span class="clinic-info-value"><?php echo htmlspecialchars($clinic['department']); ?></span>
                                </div>
                                <div class="clinic-info-item">
                                    <span class="clinic-info-label">Location:</span>
                                    <span class="clinic-info-value"><?php echo htmlspecialchars($clinic['location']); ?></span>
                                </div>
                                <div class="clinic-info-item">
                                    <span class="clinic-info-label">Consultant:</span>
                                    <span class="clinic-info-value">
                                        <?php echo $clinic['doctor_name'] ? 'Dr. ' . htmlspecialchars($clinic['doctor_name']) : 'Not assigned'; ?>
                                    </span>
                                </div>
                                <div class="clinic-info-item">
                                    <span class="clinic-info-label">Phone:</span>
                                    <span class="clinic-info-value"><?php echo htmlspecialchars($clinic['phone']); ?></span>
                                </div>
                            </div>
                            
                            <div class="clinic-stats">
                                <div class="stats-row">
                                    <span class="stat-label">Max Daily Appointments:</span>
                                    <span class="stat-value"><?php echo $clinic['max_appointments_per_day']; ?></span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">Today's Appointments:</span>
                                    <span class="stat-value"><?php echo $clinic['today_appointments']; ?></span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">Total Appointments:</span>
                                    <span class="stat-value"><?php echo $clinic['total_appointments']; ?></span>
                                </div>
                                <div class="stats-row">
                                    <span class="stat-label">Duration per Visit:</span>
                                    <span class="stat-value"><?php echo $clinic['appointment_duration']; ?> minutes</span>
                                </div>
                            </div>
                            
                            <div class="clinic-actions">
                                <a href="clinic_view.php?id=<?php echo $clinic['clinic_id']; ?>" class="btn btn-success" title="View Details">👁️</a>
                                <a href="appointment_form.php?clinic_id=<?php echo $clinic['clinic_id']; ?>" class="btn btn-primary" title="New Appointment">📅</a>
                                <a href="clinic_form.php?edit=<?php echo $clinic['clinic_id']; ?>" class="btn btn-warning" title="Edit">✏️</a>
                                <?php if ($is_admin): ?>
                                    <a href="clinic_list.php?delete=<?php echo $clinic['clinic_id']; ?>" class="btn btn-danger" title="Deactivate" onclick="return confirm('Are you sure you want to deactivate this clinic?');">🗑️</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>🏢 No Clinics Found</h3>
                    <p>Start by adding your first clinic to begin managing appointments.</p>
                    <a href="clinic_form.php" class="btn btn-primary" style="margin-top: 1rem;">➕ Add First Clinic</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>