<?php
include '../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get medicine ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: medicines.php");
    exit();
}

$medicine_id = $conn->real_escape_string($_GET['id']);
$admission_id = isset($_GET['admission_id']) ? $conn->real_escape_string($_GET['admission_id']) : null;
$admission_number = isset($_GET['admission_number']) ? $conn->real_escape_string($_GET['admission_number']) : null;

// Fetch medicine details
$sql = "SELECT m.*, u.username as prescribed_by_name
        FROM medicines m
        LEFT JOIN users u ON m.prescribed_by = u.user_id
        WHERE m.medicine_id = $medicine_id";

$result = $conn->query($sql);
if ($result->num_rows == 0) {
    header("Location: medicines.php");
    exit();
}

$medicine = $result->fetch_assoc();
if (!$admission_id) {
    $admission_id = $medicine['admission_id'];
}

// Try to get ward admission + patient
$admission_sql = "SELECT wa.*, p.calling_name, p.full_name, p.nic FROM ward_admissions wa LEFT JOIN patients p ON wa.patient_id = p.patient_id WHERE wa.admission_id = $admission_id";
$admission_res = $conn->query($admission_sql);
$is_clinic = false;
if ($admission_res && $admission_res->num_rows > 0) {
    $admission = $admission_res->fetch_assoc();
} else {
    // Try clinic admissions
    $ca_sql = "SELECT ca.*, p.calling_name, p.full_name, p.nic FROM clinic_admissions ca LEFT JOIN patients p ON ca.patient_id = p.patient_id WHERE ca.admission_id = $admission_id";
    $ca_res = $conn->query($ca_sql);
    if ($ca_res && $ca_res->num_rows > 0) {
        $admission = $ca_res->fetch_assoc();
        $is_clinic = true;
    } else {
        header("Location: medicines.php");
        exit();
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Medicine Details - <?php echo htmlspecialchars($medicine['medicine_name']); ?></title>
    <style>
        body{font-family:Segoe UI,Arial,sans-serif;padding:20px;background:#f6f8fa}
        .card{max-width:1000px;margin:0 auto;background:#fff;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.08);overflow:hidden}
        .header{background:#34495e;color:#fff;padding:20px}
        .content{padding:20px}
        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
        .info-item{background:#f8f9fa;padding:12px;border-radius:6px}
        .btn{display:inline-block;padding:10px 14px;margin-right:8px;border-radius:6px;text-decoration:none;color:#fff}
        .btn-primary{background:#3498db}
        .btn-secondary{background:#95a5a6}
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>💊 Medicine Details</h1>
        <div><?php echo htmlspecialchars($medicine['medicine_name']); ?></div>
    </div>
    <div class="content">
        <div style="margin-bottom:12px;color:#555"><strong>Patient:</strong> <?php echo htmlspecialchars($admission['calling_name']) . ' (' . htmlspecialchars($admission['full_name']) . ')'; ?> 
            | <strong>NIC:</strong> <?php echo htmlspecialchars($admission['nic']); ?> 
            <?php if ($is_clinic): ?> | <strong>Clinic Admission:</strong> <?php echo htmlspecialchars($admission['admission_number']); ?><?php else: ?> | <strong>Admission ID:</strong> #<?php echo str_pad($admission_id,4,'0',STR_PAD_LEFT); ?><?php endif; ?>
        </div>

        <div class="info-grid">
            <div class="info-item"><strong>Dosage</strong><div><?php echo htmlspecialchars($medicine['dosage']); ?></div></div>
            <div class="info-item"><strong>Route</strong><div><?php echo htmlspecialchars($medicine['route']); ?></div></div>
            <div class="info-item"><strong>Frequency</strong><div><?php echo htmlspecialchars($medicine['frequency']); ?></div></div>
            <div class="info-item"><strong>Start Date</strong><div><?php echo date('M j, Y', strtotime($medicine['start_date'])); ?> <?php if(!empty($medicine['start_time'])) echo date(' g:i A', strtotime($medicine['start_time'])); ?></div></div>
            <?php if (!empty($medicine['end_date'])): ?><div class="info-item"><strong>End Date</strong><div><?php echo date('M j, Y', strtotime($medicine['end_date'])); ?></div></div><?php endif; ?>
            <div class="info-item"><strong>Prescribed By</strong><div><?php echo htmlspecialchars($medicine['prescribed_by_name']); ?></div></div>
        </div>

        <?php if (!empty($medicine['indication'])): ?><div style="margin-top:12px;"><strong>Indication</strong><div style="background:#fff;padding:10px;border-radius:6px;margin-top:6px"><?php echo nl2br(htmlspecialchars($medicine['indication'])); ?></div></div><?php endif; ?>
        <?php if (!empty($medicine['instructions'])): ?><div style="margin-top:12px;"><strong>Instructions</strong><div style="background:#fff;padding:10px;border-radius:6px;margin-top:6px"><?php echo nl2br(htmlspecialchars($medicine['instructions'])); ?></div></div><?php endif; ?>

        <div style="margin-top:18px">
            <a href="medicine_form.php?edit=<?php echo $medicine_id; ?>&admission_id=<?php echo $admission_id; ?><?php if ($is_clinic) echo '&admission_number='.urlencode($admission['admission_number']); ?>" class="btn btn-primary">✏️ Edit</a>
            <?php if ($is_clinic): ?>
                <a href="medicines.php?admission_number=<?php echo urlencode($admission['admission_number']); ?>" class="btn btn-secondary">← Back to Medicines</a>
                <a href="../../pages/clinics/clinic_admission_form.php?admission_id=<?php echo $admission_id; ?>" class="btn btn-secondary">👁️ View Admission</a>
            <?php else: ?>
                <a href="medicines.php?admission_id=<?php echo $admission_id; ?>" class="btn btn-secondary">← Back to Medicines</a>
                <a href="admission_view.php?id=<?php echo $admission_id; ?>" class="btn btn-secondary">👁️ View Admission</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
