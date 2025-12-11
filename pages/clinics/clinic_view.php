<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$clinic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($clinic_id == 0) {
    header("Location: clinic_list.php");
    exit();
}

// Create clinic_appointments table if it doesn't exist
$create_appointments_table = "CREATE TABLE IF NOT EXISTS clinic_appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    patient_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    appointment_type VARCHAR(100),
    appointment_status ENUM('Scheduled', 'Completed', 'Cancelled', 'No Show') DEFAULT 'Scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clinic_date (clinic_id, appointment_date),
    INDEX idx_patient (patient_id)
)";
$conn->query($create_appointments_table);

// Add missing columns to clinics table if they don't exist
$add_capacity_columns = "ALTER TABLE clinics 
    ADD COLUMN IF NOT EXISTS daily_capacity INT DEFAULT 30,
    ADD COLUMN IF NOT EXISTS slot_duration_minutes INT DEFAULT 20";
$conn->query($add_capacity_columns);

// Get clinic details
$clinic_query = "SELECT c.*, d.doctor_name, u.username as consultant_name 
                FROM clinics c
                LEFT JOIN doctors d ON c.consultant_doctor_id = d.doctor_id
                LEFT JOIN users u ON d.user_id = u.user_id
                WHERE c.clinic_id = ?";
$clinic_stmt = $conn->prepare($clinic_query);
$clinic_stmt->bind_param("i", $clinic_id);
$clinic_stmt->execute();
$clinic_result = $clinic_stmt->get_result();

if ($clinic_result->num_rows == 0) {
    header("Location: clinic_list.php?error=clinic_not_found");
    exit();
}

$clinic = $clinic_result->fetch_assoc();

// Get appointment statistics for this clinic
$stats_query = "SELECT 
    COUNT(*) as total_appointments,
    COALESCE(SUM(CASE WHEN appointment_status = 'Scheduled' THEN 1 ELSE 0 END), 0) as scheduled_count,
    COALESCE(SUM(CASE WHEN appointment_status = 'Completed' THEN 1 ELSE 0 END), 0) as completed_count,
    COALESCE(SUM(CASE WHEN appointment_status = 'Cancelled' THEN 1 ELSE 0 END), 0) as cancelled_count,
    COALESCE(SUM(CASE WHEN appointment_status = 'No Show' THEN 1 ELSE 0 END), 0) as no_show_count,
    COALESCE(SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END), 0) as today_appointments,
    COALESCE(SUM(CASE WHEN DATE(appointment_date) >= CURDATE() AND appointment_status = 'Scheduled' THEN 1 ELSE 0 END), 0) as upcoming_appointments
    FROM clinic_appointments 
    WHERE clinic_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $clinic_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Handle case where no appointments exist
if (!$stats || $stats['total_appointments'] == 0) {
    $stats = [
        'total_appointments' => 0,
        'scheduled_count' => 0,
        'completed_count' => 0,
        'cancelled_count' => 0,
        'no_show_count' => 0,
        'today_appointments' => 0,
        'upcoming_appointments' => 0
    ];
}

// Get recent appointments
$recent_query = "SELECT ca.*, p.calling_name, p.full_name, p.nic
                FROM clinic_appointments ca
                JOIN patients p ON ca.patient_id = p.patient_id
                WHERE ca.clinic_id = ?
                ORDER BY ca.appointment_date DESC, ca.appointment_time DESC
                LIMIT 10";
$recent_stmt = $conn->prepare($recent_query);
$recent_stmt->bind_param("i", $clinic_id);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();

// Get today's appointments
$today_query = "SELECT ca.*, p.calling_name, p.full_name, p.nic
                FROM clinic_appointments ca
                JOIN patients p ON ca.patient_id = p.patient_id
                WHERE ca.clinic_id = ? AND DATE(ca.appointment_date) = CURDATE()
                ORDER BY ca.appointment_time ASC";
$today_stmt = $conn->prepare($today_query);
$today_stmt->bind_param("i", $clinic_id);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clinic['clinic_name']); ?> - CAPD Clinic Details</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .clinic-header {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .clinic-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #ff9a9e;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .info-card h4 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #ff9a9e;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .appointment-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: box-shadow 0.3s ease;
        }
        .appointment-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .patient-info {
            flex: 1;
        }
        .patient-name {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .patient-details {
            margin: 0.25rem 0;
            color: #666;
            font-size: 0.9rem;
        }
        .appointment-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: 1rem;
            flex-direction: column;
            align-items: flex-end;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-scheduled { background: #e3f2fd; color: #1976d2; }
        .status-completed { background: #e8f5e8; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .status-no-show { background: #fff3e0; color: #ef6c00; }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
        }
        .section-header h3 {
            margin: 0;
            color: #2c3e50;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        .empty-state h4 {
            color: #999;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        @media (max-width: 768px) {
            .clinic-info-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .appointment-card {
                padding: 1rem;
            }
            .appointment-card > div {
                flex-direction: column !important;
                align-items: flex-start !important;
            }
            .appointment-actions {
                margin-left: 0;
                margin-top: 1rem;
                flex-direction: row;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🩺 CAPD Clinic Details</h1>
            </div>
            <div>
                <a href="clinic_list.php" class="btn btn-secondary">← Back to Clinics</a>
                <a href="../../index.php" class="btn btn-secondary">🏠 Dashboard</a>
            </div>
        </div>

        <!-- Clinic Header -->
        <div class="clinic-header">
            <h2><?php echo htmlspecialchars($clinic['clinic_name']); ?></h2>
            <p style="margin: 0.5rem 0; font-size: 1.1rem; opacity: 0.9;">
                📋 Code: <?php echo htmlspecialchars($clinic['clinic_code']); ?> | 
                🏥 Department: <?php echo htmlspecialchars($clinic['department']); ?>
            </p>
            <p style="margin: 0; opacity: 0.9;">
                Status: <?php echo $clinic['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
            </p>
        </div>

        <!-- Clinic Information -->
        <div class="card">
            <h3>📋 Clinic Information</h3>
            <div class="clinic-info-grid">
                <div class="info-card">
                    <h4>📍 Location & Contact</h4>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($clinic['location'] ?: 'Not specified'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($clinic['phone'] ?: 'Not specified'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($clinic['email'] ?: 'Not specified'); ?></p>
                </div>
                <div class="info-card">
                    <h4>🕐 Operating Hours</h4>
                    <p><?php echo nl2br(htmlspecialchars($clinic['operating_hours'] ?: 'Not specified')); ?></p>
                </div>
                <div class="info-card">
                    <h4>👨‍⚕️ Consultant Doctor</h4>
                    <p><?php echo htmlspecialchars($clinic['consultant_name'] ?: 'Not assigned'); ?></p>
                </div>
                <div class="info-card">
                    <h4>👥 Capacity Settings</h4>
                    <p><strong>Daily Capacity:</strong> <?php echo intval($clinic['daily_capacity'] ?? 30); ?> patients</p>
                    <p><strong>Slot Duration:</strong> <?php echo intval($clinic['slot_duration_minutes'] ?? 20); ?> minutes</p>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="card">
            <h3>📊 Appointment Statistics</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_appointments']; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['today_appointments']; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['upcoming_appointments']; ?></div>
                    <div class="stat-label">Upcoming Scheduled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['completed_count']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['cancelled_count']; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['no_show_count']; ?></div>
                    <div class="stat-label">No Shows</div>
                </div>
            </div>
        </div>

        <!-- Today's Appointments -->
        <div class="card">
            <div class="section-header">
                <h3>📅 Today's CAPD Appointments (<?php echo $today_result->num_rows; ?>)</h3>
                <a href="appointment_form.php?clinic_id=<?php echo $clinic_id; ?>" class="btn btn-success">+ New Appointment</a>
            </div>
            
            <?php if ($today_result->num_rows > 0): ?>
                <?php while ($appointment = $today_result->fetch_assoc()): ?>
                    <div class="appointment-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div class="patient-info">
                                <h4 class="patient-name">👤 <?php echo htmlspecialchars($appointment['calling_name']); ?></h4>
                                <p class="patient-details">🆔 ID: <?php echo htmlspecialchars($appointment['nic']); ?></p>
                                <p class="patient-details">🕐 Time: <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                                <p class="patient-details">🩺 Type: <?php echo htmlspecialchars($appointment['appointment_type']); ?></p>
                                <?php if ($appointment['notes']): ?>
                                    <p class="patient-details">📝 Notes: <?php echo htmlspecialchars($appointment['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="appointment-actions">
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $appointment['appointment_status'])); ?>">
                                    <?php echo htmlspecialchars($appointment['appointment_status']); ?>
                                </span>
                                <small style="color: #999; margin-top: 0.5rem;">
                                    Today
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h4>📅 No CAPD appointments scheduled for today</h4>
                    <p>Start by scheduling a new CAPD appointment for your patients.</p>
                    <a href="appointment_form.php?clinic_id=<?php echo $clinic_id; ?>" class="btn btn-success" style="margin-top: 1rem;">📅 Schedule First Appointment</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Appointments -->
        <div class="card">
            <div class="section-header">
                <h3>📋 Recent Appointments</h3>
                <a href="appointment_list.php?clinic_id=<?php echo $clinic_id; ?>" class="btn btn-secondary">View All</a>
            </div>
            
            <?php if ($recent_result->num_rows > 0): ?>
                <?php while ($appointment = $recent_result->fetch_assoc()): ?>
                    <div class="appointment-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div class="patient-info">
                                <h4 class="patient-name">👤 <?php echo htmlspecialchars($appointment['calling_name']); ?></h4>
                                <p class="patient-details">🆔 ID: <?php echo htmlspecialchars($appointment['nic']); ?></p>
                                <p class="patient-details">📅 Date: <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></p>
                                <p class="patient-details">🕐 Time: <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                                <p class="patient-details">🩺 Type: <?php echo htmlspecialchars($appointment['appointment_type']); ?></p>
                            </div>
                            <div class="appointment-actions">
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $appointment['appointment_status'])); ?>">
                                    <?php echo htmlspecialchars($appointment['appointment_status']); ?>
                                </span>
                                <small style="color: #999; margin-top: 0.5rem;">
                                    <?php echo date('M j', strtotime($appointment['appointment_date'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h4>📋 No appointment history found</h4>
                    <p>Appointment history will appear here once appointments are scheduled.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h3>⚡ Quick Actions</h3>
            <div class="quick-actions">
                <a href="appointment_form.php?clinic_id=<?php echo $clinic_id; ?>" class="btn btn-success">📅 Schedule New Appointment</a>
                <a href="appointment_list.php?clinic_id=<?php echo $clinic_id; ?>" class="btn btn-secondary">📋 View All Appointments</a>
                <a href="clinic_form.php?id=<?php echo $clinic_id; ?>" class="btn btn-warning">✏️ Edit Clinic</a>
                <a href="clinic_list.php" class="btn btn-secondary">← Back to Clinic List</a>
            </div>
        </div>
    </div>
</body>
</html>