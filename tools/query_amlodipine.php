<?php
include __DIR__ . '/../config/db.php';
$res = $conn->query("SELECT medicine_id, patient_id, medicine_master_id, medicine_name, status, start_date, admission_id, admission_number FROM medicines WHERE LOWER(medicine_name) LIKE '%amlodipine%' LIMIT 100");
if (!$res) { echo 'Query error: ' . $conn->error . "\n"; exit(1); }
while ($r = $res->fetch_assoc()) {
    print_r($r);
}
echo "Done.\n";
