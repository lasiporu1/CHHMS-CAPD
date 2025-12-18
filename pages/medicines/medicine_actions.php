<?php
require '../../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action'])) {
    header('Location: medicine_card.php'); exit();
}
$action = $_POST['action'];
$medicine_id = isset($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : 0;
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;

if ($medicine_id) {
    if ($action === 'on_hold') {
        $conn->query("UPDATE medicines SET status = 'On Hold' WHERE medicine_id = $medicine_id");
    } elseif ($action === 'omit') {
        $conn->query("UPDATE medicines SET status = 'Omit', end_date = NOW() WHERE medicine_id = $medicine_id");
    } elseif ($action === 'continue') {
        // copy medicine to a new active entry
        $r = $conn->query("SELECT * FROM medicines WHERE medicine_id = $medicine_id");
        if ($r && $r->num_rows) {
            $m = $r->fetch_assoc();
            $mid = (int)$m['medicine_id'];
            $card = isset($m['medicine_card_id']) && $m['medicine_card_id'] ? (int)$m['medicine_card_id'] : 'NULL';
            $adid = isset($_POST['admission_id']) ? (int)$_POST['admission_id'] : (isset($m['admission_id'])?$m['admission_id']:'NULL');
            $pid_val = !empty($patient_id) ? (int)$patient_id : (isset($m['patient_id']) && $m['patient_id'] ? (int)$m['patient_id'] : 'NULL');

            // Determine medicine_master_id if present on the source row
            $m_mid = (isset($m['medicine_master_id']) && $m['medicine_master_id']) ? (int)$m['medicine_master_id'] : 'NULL';

            // Strict duplicate check: determine numeric patient id and master id if present
            $pid_num = (is_numeric($pid_val) ? (int)$pid_val : null);
            $m_mid_num = (is_numeric($m_mid) ? (int)$m_mid : null);
            if ($pid_num) {
                if ($m_mid_num) {
                    $chk = $conn->query("SELECT medicine_id FROM medicines WHERE patient_id = $pid_num AND medicine_master_id = $m_mid_num AND status = 'Active' LIMIT 1");
                    if ($chk && $chk->num_rows > 0) {
                        $err = urlencode('An active prescription for this medicine already exists for the patient.');
                        if ($patient_id) header('Location: medicine_card.php?patient_id=' . $patient_id . '&error=' . $err);
                        else header('Location: medicine_card.php?error=' . $err);
                        exit();
                    }
                }
                // fallback: name-based check
                $chk_name = $conn->real_escape_string(trim($m['medicine_name']));
                if ($chk_name !== '') {
                    $chk = $conn->query("SELECT medicine_id FROM medicines WHERE patient_id = $pid_num AND TRIM(LOWER(medicine_name)) = TRIM(LOWER('" . $chk_name . "')) AND status = 'Active' LIMIT 1");
                    if ($chk && $chk->num_rows > 0) {
                        $err = urlencode('An active prescription for this medicine already exists for the patient.');
                        if ($patient_id) header('Location: medicine_card.php?patient_id=' . $patient_id . '&error=' . $err);
                        else header('Location: medicine_card.php?error=' . $err);
                        exit();
                    }
                }
            }

            // proceed to insert including medicine_master_id when available
            $sql = "INSERT INTO medicines (admission_id, patient_id, admission_number, medicine_card_id, medicine_master_id, medicine_name, dosage, route, frequency, start_date, end_date, prescribed_by, indication, status) 
                VALUES (".($adid===0?'NULL':$adid).", " . ($pid_val==='NULL'?"NULL":$pid_val) . ", " . ($m['admission_number']?"'".$conn->real_escape_string($m['admission_number'])."'":"NULL") . ", $card, " . ($m_mid !== 'NULL' ? $m_mid : 'NULL') . ", '".$conn->real_escape_string($m['medicine_name'])."', '".$conn->real_escape_string($m['dosage'])."', '".$conn->real_escape_string($m['route'])."', '".$conn->real_escape_string($m['frequency'])."', DATE(NOW()), NULL, " . $_SESSION['user_id'] . ", '".$conn->real_escape_string($m['indication'])."', 'Active')";
            $conn->query($sql);
        }
    }
}

// Redirect back to medicine card
if ($patient_id) header('Location: medicine_card.php?patient_id=' . $patient_id);
else header('Location: medicine_card.php');
exit();
