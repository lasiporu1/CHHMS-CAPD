<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get investigation ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: investigations.php");
    exit();
}

$investigation_id = $conn->real_escape_string($_GET['id']);
$admission_id = isset($_GET['admission_id']) ? $conn->real_escape_string($_GET['admission_id']) : null;

// Fetch investigation details
$sql = "SELECT i.*, 
               u1.username as ordered_by_name,
               wa.admission_id, wa.ward_bed,
               p.calling_name, p.full_name, p.nic
        FROM investigations i
        LEFT JOIN users u1 ON i.ordered_by = u1.user_id
        LEFT JOIN ward_admissions wa ON i.admission_id = wa.admission_id
        LEFT JOIN patients p ON wa.patient_id = p.patient_id
        WHERE i.investigation_id = $investigation_id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: investigations.php" . ($admission_id ? "?admission_id=$admission_id" : ""));
    exit();
}

$investigation = $result->fetch_assoc();
if (!$admission_id) {
    $admission_id = $investigation['admission_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investigation Details - <?php echo htmlspecialchars($investigation['investigation_name']); ?></title>
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
            padding: 2rem;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .content {
            padding: 2rem;
        }
        
        .section {
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        
        .section-content {
            padding: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #495057;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #667eea;
            font-size: 1rem;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-in-progress { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .urgent-badge {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 2rem;
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
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .result-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .result-values {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            margin: 1rem 0;
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-title {
            font-weight: 600;
            color: #495057;
        }
        
        .timeline-time {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔬 Investigation Details</h1>
            <p>Detailed view of laboratory investigation</p>
        </div>
        
        <div class="content">
            <!-- Patient Information -->
            <div class="section">
                <div class="section-header">👤 Patient Information</div>
                <div class="section-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Patient Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($investigation['calling_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($investigation['full_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">NIC</div>
                            <div class="info-value"><?php echo htmlspecialchars($investigation['nic']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ward Bed</div>
                            <div class="info-value"><?php echo htmlspecialchars($investigation['ward_bed'] ?: 'Not assigned'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Investigation Details -->
            <div class="section">
                <div class="section-header">🔬 Investigation Information</div>
                <div class="section-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Investigation Name</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($investigation['investigation_name']); ?>
                                <?php if ($investigation['urgent']): ?>
                                    <span class="urgent-badge">URGENT</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Investigation Type</div>
                            <div class="info-value"><?php echo htmlspecialchars($investigation['investigation_type']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ordered Date & Time</div>
                            <div class="info-value">
                                <?php echo date('F j, Y', strtotime($investigation['ordered_date'])); ?><br>
                                <?php echo date('g:i A', strtotime($investigation['ordered_time'])); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ordered By</div>
                            <div class="info-value"><?php echo htmlspecialchars($investigation['ordered_by_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Current Status</div>
                            <div class="info-value">
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $investigation['result_status'])); ?>">
                                    <?php echo $investigation['result_status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sample Collection -->
            <div class="section">
                <div class="section-header">🧪 Sample Collection</div>
                <div class="section-content">
                    <?php if ($investigation['sample_collected']): ?>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Collection Status</div>
                                <div class="info-value">
                                    <span class="status-badge status-completed">✅ Sample Collected</span>
                                </div>
                            </div>
                            <?php if ($investigation['collection_date']): ?>
                            <div class="info-item">
                                <div class="info-label">Collection Date</div>
                                <div class="info-value"><?php echo date('F j, Y', strtotime($investigation['collection_date'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($investigation['collection_time']): ?>
                            <div class="info-item">
                                <div class="info-label">Collection Time</div>
                                <div class="info-value"><?php echo date('g:i A', strtotime($investigation['collection_time'])); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="timeline-item" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                            <div class="timeline-icon" style="background: #ffc107; color: white;">⏳</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Sample Collection Pending</div>
                                <div class="timeline-time">Sample not yet collected from patient</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Results Section -->
            <div class="section">
                <div class="section-header">📋 Investigation Results</div>
                <div class="section-content">
                    <?php if ($investigation['result_status'] == 'Completed' && !empty($investigation['result_values'])): ?>
                        <div class="info-grid">
                            <?php if ($investigation['result_date']): ?>
                            <div class="info-item">
                                <div class="info-label">Result Date</div>
                                <div class="info-value"><?php echo date('F j, Y', strtotime($investigation['result_date'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($investigation['result_time']): ?>
                            <div class="info-item">
                                <div class="info-label">Result Time</div>
                                <div class="info-value"><?php echo date('g:i A', strtotime($investigation['result_time'])); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($investigation['normal_range']): ?>
                            <div class="info-item">
                                <div class="info-label">Normal Range</div>
                                <div class="info-value"><?php echo htmlspecialchars($investigation['normal_range']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="result-section">
                            <h4 style="margin-bottom: 1rem; color: #495057;">📊 Test Results</h4>
                            <div class="result-values"><?php echo htmlspecialchars($investigation['result_values']); ?></div>
                            
                            <?php if ($investigation['interpretation']): ?>
                            <h4 style="margin: 1.5rem 0 1rem 0; color: #495057;">🔍 Clinical Interpretation</h4>
                            <div style="background: white; padding: 1rem; border-radius: 6px; border: 1px solid #dee2e6;">
                                <?php echo nl2br(htmlspecialchars($investigation['interpretation'])); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($investigation['remarks']): ?>
                            <h4 style="margin: 1.5rem 0 1rem 0; color: #495057;">💬 Additional Remarks</h4>
                            <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; border-left: 3px solid #667eea;">
                                <?php echo nl2br(htmlspecialchars($investigation['remarks'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="timeline-item" style="background: #cce5ff; border-left: 4px solid #007bff;">
                            <div class="timeline-icon" style="background: #007bff; color: white;">⏰</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Results Pending</div>
                                <div class="timeline-time">
                                    Status: <?php echo $investigation['result_status']; ?>
                                    <?php if ($investigation['result_status'] == 'Cancelled'): ?>
                                        - Investigation has been cancelled
                                    <?php else: ?>
                                        - Results will be available once processing is complete
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="section">
                <div class="section-header">📅 Investigation Timeline</div>
                <div class="section-content">
                    <div class="timeline-item">
                        <div class="timeline-icon" style="background: #28a745; color: white;">📝</div>
                        <div class="timeline-content">
                            <div class="timeline-title">Investigation Ordered</div>
                            <div class="timeline-time">
                                <?php echo date('F j, Y \a\t g:i A', strtotime($investigation['ordered_date'] . ' ' . $investigation['ordered_time'])); ?>
                                by <?php echo htmlspecialchars($investigation['ordered_by_name']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($investigation['sample_collected']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon" style="background: #007bff; color: white;">🧪</div>
                        <div class="timeline-content">
                            <div class="timeline-title">Sample Collected</div>
                            <div class="timeline-time">
                                <?php if ($investigation['collection_date'] && $investigation['collection_time']): ?>
                                    <?php echo date('F j, Y \a\t g:i A', strtotime($investigation['collection_date'] . ' ' . $investigation['collection_time'])); ?>
                                <?php else: ?>
                                    Sample collected (time not recorded)
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($investigation['result_status'] == 'Completed'): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon" style="background: #28a745; color: white;">✅</div>
                        <div class="timeline-content">
                            <div class="timeline-title">Results Available</div>
                            <div class="timeline-time">
                                <?php if ($investigation['result_date'] && $investigation['result_time']): ?>
                                    <?php echo date('F j, Y \a\t g:i A', strtotime($investigation['result_date'] . ' ' . $investigation['result_time'])); ?>
                                <?php else: ?>
                                    Results completed
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($investigation['result_status'] == 'Cancelled'): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon" style="background: #dc3545; color: white;">❌</div>
                        <div class="timeline-content">
                            <div class="timeline-title">Investigation Cancelled</div>
                            <div class="timeline-time">Investigation was cancelled</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="btn-group">
                <a href="investigation_form.php?edit=<?php echo $investigation_id; ?>&admission_id=<?php echo $admission_id; ?>" class="btn btn-warning">✏️ Edit Investigation</a>
                <a href="investigations.php?admission_id=<?php echo $admission_id; ?>" class="btn btn-secondary">← Back to Investigations</a>
                <a href="admission_view.php?id=<?php echo $admission_id; ?>" class="btn btn-primary">👁️ View Admission</a>
            </div>
        </div>
    </div>
</body>
</html>