<?php
// Run this script from CLI or web to add medicine_master_id to medicines and backfill
include __DIR__ . '/../config/db.php';
echo "Starting schema update: add medicine_master_id to medicines...\n";

$check = $conn->query("SHOW COLUMNS FROM medicines LIKE 'medicine_master_id'");
if ($check && $check->num_rows > 0) {
    echo "Column medicine_master_id already exists.\n";
} else {
    $alter = "ALTER TABLE medicines ADD COLUMN medicine_master_id INT NULL";
    if ($conn->query($alter) === TRUE) {
        echo "Added column medicine_master_id.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
        exit(1);
    }
}

// Backfill: for each distinct medicine_name in medicines, try to find master by name
echo "Starting backfill from medicine_master by name...\n";
$res = $conn->query("SELECT DISTINCT medicine_name FROM medicines WHERE medicine_name IS NOT NULL AND medicine_name <> ''");
if ($res && $res->num_rows > 0) {
    $count = 0;
    while ($r = $res->fetch_assoc()) {
        $mname = $conn->real_escape_string($r['medicine_name']);
        $mres = $conn->query("SELECT master_id FROM medicine_master WHERE LOWER(medicine_name) = LOWER('" . $mname . "') LIMIT 1");
        if ($mres && $mres->num_rows > 0) {
            $mid = (int)$mres->fetch_assoc()['master_id'];
            $u = $conn->query("UPDATE medicines SET medicine_master_id = $mid WHERE LOWER(medicine_name) = LOWER('" . $mname . "') AND (medicine_master_id IS NULL OR medicine_master_id = 0)");
            if ($u) $count += $conn->affected_rows;
        }
    }
    echo "Backfilled $count rows with medicine_master_id.\n";
} else {
    echo "No medicines rows found to backfill.\n";
}

echo "Done.\n";

?>
