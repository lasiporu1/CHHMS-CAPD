<?php
require __DIR__ . '/../config/db.php';

echo "Starting medicines->medicine_cards migration...\n";

// 1) create medicine_cards table
$sql = "CREATE TABLE IF NOT EXISTS medicine_cards (
    medicine_card_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "medicine_cards table OK\n";
} else {
    echo "Error creating medicine_cards: " . $conn->error . "\n";
}

// 2) add medicine_card_id to medicines if missing
$check = $conn->query("SHOW COLUMNS FROM medicines LIKE 'medicine_card_id'");
if ($check && $check->num_rows == 0) {
    if ($conn->query("ALTER TABLE medicines ADD COLUMN medicine_card_id INT NULL") === TRUE) {
        echo "Added medicines.medicine_card_id\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "medicines.medicine_card_id already exists\n";
}

// 3) Backfill medicine_card for existing medicines grouped by patient
$map = [];
$res = $conn->query("SELECT m.medicine_id, COALESCE(wa.patient_id, ca.patient_id) AS patient_id
FROM medicines m
LEFT JOIN ward_admissions wa ON m.admission_id = wa.admission_id
LEFT JOIN clinic_admissions ca ON m.admission_number = ca.admission_number
WHERE COALESCE(wa.patient_id, ca.patient_id) IS NOT NULL");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $mid = (int)$row['medicine_id'];
        $pid = (int)$row['patient_id'];
        if (!isset($map[$pid])) $map[$pid] = [];
        $map[$pid][] = $mid;
    }
    echo "Found " . count($map) . " patients with medicines to backfill\n";
    foreach ($map as $pid => $mids) {
        // create card
        $conn->query("INSERT INTO medicine_cards (patient_id) VALUES ($pid)");
        $card_id = $conn->insert_id;
        if ($card_id) {
            $ids = implode(',', $mids);
            $u = $conn->query("UPDATE medicines SET medicine_card_id = $card_id WHERE medicine_id IN ($ids)");
            echo "Backfilled patient $pid -> card $card_id (" . count($mids) . " meds)\n";
        }
    }
} else {
    echo "No medicines found or query failed: " . $conn->error . "\n";
}

echo "Migration complete.\n";

?>
