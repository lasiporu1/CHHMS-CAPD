<?php
include '../../config/db.php';
include '../../includes/header.php';

// Get all PET records with patient information
$sql = "SELECT pet.*, p.calling_name, p.full_name, p.nic, p.hospital_number, p.clinic_number,
               u.username as created_by_name
        FROM peritoneal_equilibration_test pet
        JOIN patients p ON pet.patient_id = p.patient_id
        LEFT JOIN users u ON pet.created_by = u.user_id
        ORDER BY pet.test_date DESC, pet.created_at DESC";

$result = $conn->query($sql);
$all_pet_records = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $all_pet_records[] = $row;
    }
}

// Get statistics
$total_tests = count($all_pet_records);
$unique_patients = array_unique(array_column($all_pet_records, 'patient_id'));
$total_patients = count($unique_patients);

// Count by PET levels
$level_counts = [
    'High' => 0,
    'High Average' => 0,
    'Low Average' => 0,
    'Low' => 0
];

foreach ($all_pet_records as $record) {
    if (isset($level_counts[$record['pet_level']])) {
        $level_counts[$record['pet_level']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PET Test Report</title>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
            }
            .container {
                padding: 0 !important;
            }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
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
                <div class="stat-value"><?php echo $total_tests; ?></div>
                <div class="stat-label">Total PET Tests</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div class="stat-value"><?php echo $total_patients; ?></div>
                <div class="stat-label">Unique Patients</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                <div class="stat-value"><?php echo $level_counts['High']; ?></div>
                <div class="stat-label">High Transporters</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); color: white;">
                <div class="stat-value"><?php echo $level_counts['High Average']; ?></div>
                <div class="stat-label">High Average</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;">
                <div class="stat-value"><?php echo $level_counts['Low Average']; ?></div>
                <div class="stat-label">Low Average</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%); color: #333;">
                <div class="stat-value"><?php echo $level_counts['Low']; ?></div>
                <div class="stat-label">Low Transporters</div>
            </div>
        </div>

        <!-- PET Records Table -->
        <div class="card">
            <h3>📊 All PET Test Records</h3>
            
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
                                <th style="padding: 1rem; text-align: left;">PET Level</th>
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
                                    <td style="padding: 1rem;">
                                        <?php 
                                        $level_colors = [
                                            'High' => 'background: #ffebee; color: #c62828;',
                                            'High Average' => 'background: #fff3e0; color: #e65100;',
                                            'Low Average' => 'background: #e3f2fd; color: #1565c0;',
                                            'Low' => 'background: #e8f5e9; color: #2e7d32;'
                                        ];
                                        $style = $level_colors[$record['pet_level']] ?? 'background: #f5f5f5; color: #666;';
                                        ?>
                                        <span style="<?php echo $style; ?> padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem; white-space: nowrap;">
                                            <?php echo htmlspecialchars($record['pet_level']); ?>
                                        </span>
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
