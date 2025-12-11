<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get admission ID from URL
if (!isset($_GET['admission_id']) || empty($_GET['admission_id'])) {
    header("Location: admission_list.php");
    exit();
}

$admission_id = $conn->real_escape_string($_GET['admission_id']);

// Fetch admission details with all related information
$admission_sql = "SELECT wa.*, 
                         p.calling_name, p.full_name, p.nic, p.hospital_number, p.clinic_number,
                         p.date_of_birth, p.contact_number, p.address,
                         ar.reason_name, ar.description as reason_description,
                         d.doctor_name, d.specialization, d.contact_number as doctor_contact,
                         no.nursing_name, no.contact_number as nurse_contact,
                         u.username as created_by_name
                  FROM ward_admissions wa
                  LEFT JOIN patients p ON wa.patient_id = p.patient_id
                  LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
                  LEFT JOIN doctors d ON wa.attending_doctor_id = d.doctor_id
                  LEFT JOIN nursing_officers no ON wa.nursing_officer_id = no.nursing_id
                  LEFT JOIN users u ON wa.created_by = u.user_id
                  WHERE wa.admission_id = $admission_id";

$admission_result = $conn->query($admission_sql);
if ($admission_result->num_rows == 0) {
    header("Location: admission_list.php");
    exit();
}
$admission = $admission_result->fetch_assoc();

// Calculate age
$age = '';
if (!empty($admission['date_of_birth'])) {
    $dob = new DateTime($admission['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y . ' years';
}

// Get investigations
$investigations_sql = "SELECT i.*, u.username as ordered_by_name
                       FROM investigations i
                       LEFT JOIN users u ON i.ordered_by = u.user_id
                       WHERE i.admission_id = $admission_id
                       ORDER BY i.ordered_date DESC, i.ordered_time DESC";
$investigations_result = $conn->query($investigations_sql);

// Get medicines
$medicines_sql = "SELECT m.*, u.username as prescribed_by_name
                  FROM medicines m
                  LEFT JOIN users u ON m.prescribed_by = u.user_id
                  WHERE m.admission_id = $admission_id
                  ORDER BY m.start_date DESC";
$medicines_result = $conn->query($medicines_sql);

// Get reports (if table exists)
$reports_result = null;
$check_reports_table = "SHOW TABLES LIKE 'reports'";
if ($conn->query($check_reports_table)->num_rows > 0) {
    $reports_sql = "SELECT r.*, u.username as created_by_name
                    FROM reports r
                    LEFT JOIN users u ON r.created_by = u.user_id
                    WHERE r.admission_id = $admission_id
                    ORDER BY r.report_date DESC";
    $reports_result = $conn->query($reports_sql);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Report - <?php echo htmlspecialchars($admission['calling_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background: #f8f9fa;
            color: #333;
        }
        
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .hospital-logo {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .hospital-name {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .report-title {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-top: 1rem;
        }
        
        .report-content {
            padding: 2rem;
        }
        
        .section {
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .section-content {
            padding: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #333;
            font-size: 1rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #667eea;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th, .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.9rem;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #cce5ff; color: #004085; }
        .status-discontinued { background: #f8d7da; color: #721c24; }
        
        .no-data {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 2rem;
        }
        
        .print-buttons {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 0 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        @media print {
            .print-buttons {
                display: none;
            }
            body {
                background: white;
            }
            .report-container {
                box-shadow: none;
                max-width: none;
            }
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Report Header -->
        <div class="report-header">
            <div class="hospital-logo">🏥</div>
            <div class="hospital-name">Ward Management System</div>
            <div style="font-size: 1rem; opacity: 0.8;">Comprehensive Patient Report</div>
            <div class="report-title">
                Patient: <?php echo htmlspecialchars($admission['calling_name']); ?> | 
                Admission ID: #<?php echo str_pad($admission['admission_id'], 4, '0', STR_PAD_LEFT); ?>
            </div>
            <div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.8;">
                Generated on: <?php echo date('F j, Y \a\t g:i A'); ?>
            </div>
        </div>

        <div class="report-content">
            <!-- Summary Statistics -->
            <div class="section">
                <div class="section-header">📊 Summary Statistics</div>
                <div class="section-content">
                    <div class="summary-stats">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $investigations_result->num_rows; ?></div>
                            <div class="stat-label">Total Investigations</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $medicines_result->num_rows; ?></div>
                            <div class="stat-label">Total Medicines</div>
                        </div>
                        <?php if ($reports_result): ?>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $reports_result->num_rows; ?></div>
                            <div class="stat-label">Total Reports</div>
                        </div>
                        <?php endif; ?>
                        <div class="stat-card">
                            <div class="stat-number">
                                <?php 
                                if ($admission['admission_status'] == 'Active') {
                                    $admission_date = new DateTime($admission['admission_date']);
                                    $today = new DateTime();
                                    echo $today->diff($admission_date)->days;
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                            <div class="stat-label">Days in Ward</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patient Information -->
            <div class="section">
                <div class="section-header">👤 Patient Information</div>
                <div class="section-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['full_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Calling Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['calling_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">NIC Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['nic']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Hospital Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['hospital_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Clinic Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['clinic_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Age</div>
                            <div class="info-value"><?php echo $age; ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Contact Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['contact_number']); ?></div>
                        </div>
                        <div class="info-item" style="grid-column: span 2;">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['address']); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admission Details -->
            <div class="section">
                <div class="section-header">🏥 Admission Details</div>
                <div class="section-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Admission Date</div>
                            <div class="info-value"><?php echo date('F j, Y', strtotime($admission['admission_date'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Admission Time</div>
                            <div class="info-value"><?php echo date('g:i A', strtotime($admission['admission_time'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ward Bed</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['ward_bed'] ?: 'Not assigned'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="status-badge status-<?php echo strtolower($admission['admission_status']); ?>">
                                    <?php echo $admission['admission_status']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Admission Reason</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['reason_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Attending Doctor</div>
                            <div class="info-value">
                                Dr. <?php echo htmlspecialchars($admission['doctor_name']); ?>
                                <?php if ($admission['specialization']): ?>
                                    <br><small>(<?php echo htmlspecialchars($admission['specialization']); ?>)</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Nursing Officer</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['nursing_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Admitted By</div>
                            <div class="info-value"><?php echo htmlspecialchars($admission['created_by_name']); ?></div>
                        </div>
                    </div>
                    <?php if ($admission['reason_description']): ?>
                    <div style="margin-top: 1rem;">
                        <div class="info-label">Reason Description</div>
                        <div class="info-value"><?php echo htmlspecialchars($admission['reason_description']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Investigations -->
            <div class="section">
                <div class="section-header">🔬 Laboratory Investigations</div>
                <div class="section-content">
                    <?php if ($investigations_result->num_rows > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Investigation</th>
                                    <th>Type</th>
                                    <th>Ordered Date</th>
                                    <th>Sample Status</th>
                                    <th>Result Status</th>
                                    <th>Results</th>
                                    <th>Ordered By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($investigation = $investigations_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($investigation['investigation_name']); ?></strong>
                                        <?php if ($investigation['urgent']): ?>
                                            <span class="status-badge" style="background: #f8d7da; color: #721c24; margin-left: 0.5rem;">URGENT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($investigation['investigation_type']); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($investigation['ordered_date'])); ?><br>
                                        <small><?php echo date('g:i A', strtotime($investigation['ordered_time'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($investigation['sample_collected']): ?>
                                            <span class="status-badge status-completed">Collected</span>
                                            <?php if ($investigation['collection_date']): ?>
                                                <br><small><?php echo date('M j, Y', strtotime($investigation['collection_date'])); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($investigation['result_status']); ?>">
                                            <?php echo $investigation['result_status']; ?>
                                        </span>
                                        <?php if ($investigation['result_date']): ?>
                                            <br><small><?php echo date('M j, Y', strtotime($investigation['result_date'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($investigation['result_values']): ?>
                                            <div><?php echo htmlspecialchars($investigation['result_values']); ?></div>
                                            <?php if ($investigation['normal_range']): ?>
                                                <small>Normal: <?php echo htmlspecialchars($investigation['normal_range']); ?></small>
                                            <?php endif; ?>
                                            <?php if ($investigation['interpretation']): ?>
                                                <br><small><em><?php echo htmlspecialchars($investigation['interpretation']); ?></em></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <em style="color: #6c757d;">Awaiting results</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($investigation['ordered_by_name']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">No investigations ordered for this admission.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Medications -->
            <div class="section">
                <div class="section-header">💊 Medications</div>
                <div class="section-content">
                    <?php if ($medicines_result->num_rows > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Dosage & Route</th>
                                    <th>Schedule</th>
                                    <th>Duration</th>
                                    <th>Indication</th>
                                    <th>Status</th>
                                    <th>Prescribed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($medicine = $medicines_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($medicine['medicine_name']); ?></strong>
                                        <?php if ($medicine['generic_name']): ?>
                                            <br><small><?php echo htmlspecialchars($medicine['generic_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($medicine['dosage']); ?><br>
                                        <small><?php echo htmlspecialchars($medicine['route']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($medicine['frequency']); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($medicine['start_date'])); ?>
                                        <?php if ($medicine['end_date']): ?>
                                            <br>to<br>
                                            <?php echo date('M j, Y', strtotime($medicine['end_date'])); ?>
                                        <?php endif; ?>
                                        <?php if ($medicine['duration']): ?>
                                            <br><small><?php echo htmlspecialchars($medicine['duration']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($medicine['indication']): ?>
                                            <?php echo htmlspecialchars($medicine['indication']); ?>
                                        <?php else: ?>
                                            <em style="color: #6c757d;">Not specified</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($medicine['status']); ?>">
                                            <?php echo $medicine['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($medicine['prescribed_by_name']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">No medications prescribed for this admission.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Medical Reports -->
            <?php if ($reports_result && $reports_result->num_rows > 0): ?>
            <div class="section">
                <div class="section-header">📋 Medical Reports</div>
                <div class="section-content">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Report Type</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($report = $reports_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['report_type']); ?></td>
                                <td><?php echo htmlspecialchars($report['report_title']); ?></td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($report['report_date'])); ?><br>
                                    <small><?php echo date('g:i A', strtotime($report['report_time'])); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($report['status']); ?>">
                                        <?php echo $report['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($report['created_by_name']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Print Buttons -->
        <div class="print-buttons">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
            <a href="admission_view.php?id=<?php echo $admission_id; ?>" class="btn btn-secondary">← Back to Admission</a>
        </div>
    </div>

    <script>
        // Auto-print functionality (uncomment if needed)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>