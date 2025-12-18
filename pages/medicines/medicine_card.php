<?php
require '../../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit(); }

$patient_id = null;
if (!empty($_GET['patient_id'])) $patient_id = (int)$_GET['patient_id'];
elseif (!empty($_GET['admission_id'])) {
    $aid = (int)$_GET['admission_id'];
    $r = $conn->query("SELECT patient_id FROM ward_admissions WHERE admission_id = $aid");
    if ($r && $r->num_rows) $patient_id = $r->fetch_assoc()['patient_id'];
}

if (!$patient_id) {
    echo "Patient not specified."; exit();
}

// find or create medicine card
$r = $conn->query("SELECT * FROM medicine_cards WHERE patient_id = $patient_id ORDER BY created_at LIMIT 1");
if ($r && $r->num_rows) {
    $card = $r->fetch_assoc();
    $card_id = $card['medicine_card_id'];
} else {
    $conn->query("INSERT INTO medicine_cards (patient_id) VALUES ($patient_id)");
    $card_id = $conn->insert_id;
}

$patientR = $conn->query("SELECT * FROM patients WHERE patient_id = $patient_id");
$patient = $patientR ? $patientR->fetch_assoc() : null;

// load medicines
$list = $conn->query("SELECT * FROM medicines WHERE medicine_card_id = $card_id ORDER BY created_at DESC");

// If embed mode requested, output only the table fragment
if (!empty($_GET['embed'])) {
    echo '<div class="medicine-card-embed">';
    echo '<h3>Medicine Card for ' . htmlspecialchars($patient['calling_name'] . ' (' . $patient['full_name'] . ')') . '</h3>';
    echo '<table style="width:100%; border-collapse:collapse;">';
    echo '<thead><tr style="background:#f1f1f1;text-align:left;"><th style="padding:8px;border:1px solid #e6e6e6;">Medicine</th><th style="padding:8px;border:1px solid #e6e6e6;">Dosage</th><th style="padding:8px;border:1px solid #e6e6e6;">Frequency</th><th style="padding:8px;border:1px solid #e6e6e6;">Route</th><th style="padding:8px;border:1px solid #e6e6e6;">Start</th><th style="padding:8px;border:1px solid #e6e6e6;">End</th><th style="padding:8px;border:1px solid #e6e6e6;">Status</th><th style="padding:8px;border:1px solid #e6e6e6;">Actions</th></tr></thead>';
    echo '<tbody>';
    if ($list && $list->num_rows) {
        while ($m = $list->fetch_assoc()) {
            echo '<tr>';
            echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($m['medicine_name']) . '</td>';
            echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($m['dosage']) . '</td>';
            echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($m['frequency']) . '</td>';
            echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($m['route']) . '</td>';
            echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($m['start_date']) . '</td>';
            echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . ($m['end_date'] ? htmlspecialchars($m['end_date']) : '') . '</td>';
            echo '<td style="padding:8px;border:1px solid #e6e6e6;">' . htmlspecialchars($m['status']) . '</td>';
            echo '<td style="padding:8px;border:1px solid #e6e6e6;"><form method="post" action="medicine_actions.php" style="display:inline"><input type="hidden" name="medicine_id" value="' . $m['medicine_id'] . '"><input type="hidden" name="patient_id" value="' . $patient_id . '"><button name="action" value="continue">Continue</button> <button name="action" value="on_hold">On Hold</button> <button name="action" value="omit">Omit</button></form> <a href="../../pages/admissions/medicine_form.php?edit=' . $m['medicine_id'] . '&admission_id=' . (isset($_GET['admission_id'])?(int)$_GET['admission_id']:'') . '">Edit</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="8" style="padding:12px;text-align:center;color:#666;">No medicines</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
    return;
}

?>
