<?php
require '../config/db.php';
session_start(); if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if (!$patient_id) { echo 'Patient required'; exit(); }

$p = $conn->query("SELECT * FROM patients WHERE patient_id = $patient_id")->fetch_assoc();
$res = $conn->query("SELECT m.*, mc.created_at as card_created FROM medicines m LEFT JOIN medicine_cards mc ON m.medicine_card_id = mc.medicine_card_id WHERE m.medicine_card_id IN (SELECT medicine_card_id FROM medicine_cards WHERE patient_id = $patient_id) ORDER BY m.created_at DESC");
?>
<!doctype html><html><head><meta charset="utf-8"><title>Medicine History</title></head><body>
<h2>Medicine History for <?php echo htmlspecialchars($p['calling_name'] . ' (' . $p['full_name'] . ')'); ?></h2>
<table border="1" cellpadding="6"><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Route</th><th>Start</th><th>End</th><th>Status</th><th>Prescribed</th></tr>
<?php if ($res && $res->num_rows) {
    while($r = $res->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($r['medicine_name']) . '</td>';
        echo '<td>' . htmlspecialchars($r['dosage']) . '</td>';
        echo '<td>' . htmlspecialchars($r['frequency']) . '</td>';
        echo '<td>' . htmlspecialchars($r['route']) . '</td>';
        echo '<td>' . htmlspecialchars($r['start_date']) . '</td>';
        echo '<td>' . ($r['end_date']?htmlspecialchars($r['end_date']):'') . '</td>';
        echo '<td>' . htmlspecialchars($r['status']) . '</td>';
        echo '<td>' . htmlspecialchars($r['created_at']) . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="8">No records</td></tr>';
}
?></table>
</body></html>
