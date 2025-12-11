<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Check if admission ID is provided
if (!isset($_GET['id'])) {
    header("Location: admission_list.php?deleted=error");
    exit();
}

$admission_id = intval($_GET['id']);

// Check if this is a confirmation request
if (isset($_GET['confirm']) && $_GET['confirm'] == '1') {
    // Begin transaction to ensure data integrity
    $conn->begin_transaction();
    
    try {
        // First, delete related records in dependent tables
        
        // Delete investigations
        $delete_investigations = "DELETE FROM investigations WHERE admission_id = ?";
        $stmt = $conn->prepare($delete_investigations);
        $stmt->bind_param("i", $admission_id);
        $stmt->execute();
        
        // Delete reports
        $delete_reports = "DELETE FROM reports WHERE admission_id = ?";
        $stmt = $conn->prepare($delete_reports);
        $stmt->bind_param("i", $admission_id);
        $stmt->execute();
        
        // Delete medicines
        $delete_medicines = "DELETE FROM medicines WHERE admission_id = ?";
        $stmt = $conn->prepare($delete_medicines);
        $stmt->bind_param("i", $admission_id);
        $stmt->execute();
        
        // Finally, delete the admission record
        $delete_admission = "DELETE FROM ward_admissions WHERE admission_id = ?";
        $stmt = $conn->prepare($delete_admission);
        $stmt->bind_param("i", $admission_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            header("Location: admission_list.php?deleted=success");
        } else {
            $conn->rollback();
            header("Location: admission_list.php?deleted=error");
        }
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: admission_list.php?deleted=error");
    }
    exit();
}

// Get admission details for confirmation
$sql = "SELECT wa.*, 
               p.calling_name, p.full_name, p.nic,
               ar.reason_name
        FROM ward_admissions wa
        LEFT JOIN patients p ON wa.patient_id = p.patient_id
        LEFT JOIN admission_reasons ar ON wa.admission_reason_id = ar.reason_id
        WHERE wa.admission_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admission_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: admission_list.php?deleted=error");
    exit();
}

$admission = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Admission - Ward</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        
        .warning-icon {
            font-size: 3rem;
            color: #e74c3c;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .admission-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #e74c3c;
        }
        
        .admission-info h3 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .admission-info p {
            margin: 0.25rem 0;
            color: #555;
        }
        
        .warning-text {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 1px solid #ffeaa7;
            font-weight: 500;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
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
            min-width: 120px;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(149, 165, 166, 0.3);
        }
        
        .admission-id {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="warning-icon">⚠️</div>
        <h2>Delete Admission Record</h2>
        
        <div class="admission-info">
            <h3>Admission Details</h3>
            <p><strong>Admission ID:</strong> <span class="admission-id">#<?php echo str_pad($admission['admission_id'], 4, '0', STR_PAD_LEFT); ?></span></p>
            <p><strong>Patient:</strong> <?php echo $admission['calling_name'] . ' (' . $admission['full_name'] . ')'; ?></p>
            <p><strong>NIC:</strong> <?php echo $admission['nic']; ?></p>
            <p><strong>Reason:</strong> <?php echo $admission['reason_name']; ?></p>
            <p><strong>Admission Date:</strong> <?php echo date('M j, Y', strtotime($admission['admission_date'])); ?></p>
            <p><strong>Ward Bed:</strong> <?php echo $admission['ward_bed'] ?: 'Not assigned'; ?></p>
        </div>
        
        <div class="warning-text">
            ⚠️ <strong>Warning:</strong> This action will permanently delete this admission record and all associated data including investigations, reports, and medicines. This action cannot be undone.
        </div>
        
        <div class="btn-group">
            <a href="admission_list.php" class="btn btn-secondary">❌ Cancel</a>
            <a href="delete_admission.php?id=<?php echo $admission_id; ?>&confirm=1" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to delete this admission record? This cannot be undone!');">🗑️ Delete</a>
        </div>
    </div>
</body>
</html>